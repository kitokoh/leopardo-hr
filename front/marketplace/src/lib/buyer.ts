/**
 * Session compte acheteur — Leopardo Marché (#7814, migration #8096).
 *
 * #8096 — CIBLE ATTEINTE : la session est portée par un cookie
 * `HttpOnly; Secure; SameSite=None` posé par l'API — le JETON N'EST PLUS
 * JAMAIS ÉCRIT en localStorage (une XSS ne peut plus l'exfiltrer ; le
 * compromis assumé #7979 est résolu).
 *
 * Seul le PROFIL PUBLIC (nom, email affichés à l'écran) reste stocké comme
 * indice d'affichage instantané (`leopardo_marche_buyer`) : ce n'est pas
 * une credential — sans cookie valide, l'API répond 401 et l'indice est
 * purgé. Même pattern que le panier : store externe + CustomEvent pour
 * synchroniser les composants du même onglet, événement natif `storage`
 * pour les autres onglets.
 *
 * Migration douce : les sessions legacy `{token, buyer}` sont détectées à
 * la lecture — le jeton est échangé UNE fois contre le cookie via
 * `restoreBuyerSession` (`takeLegacyBuyerToken`), puis purgé du stockage.
 */

import type { BuyerProfile } from "@/lib/api";

export const BUYER_STORAGE_KEY = "leopardo_marche_buyer";
export const BUYER_EVENT = "leopardo-marche:buyer";

export interface BuyerSessionState {
  buyer: BuyerProfile;
}

/** Forme legacy (avant #8096) : le jeton était persisté en clair. */
interface LegacyBuyerSessionState {
  token: string;
  buyer: BuyerProfile;
}

function isBrowser(): boolean {
  return typeof window !== "undefined";
}

function isValidBuyer(buyer: unknown): buyer is BuyerProfile {
  if (typeof buyer !== "object" || buyer === null) return false;
  const candidate = buyer as Partial<BuyerProfile>;
  return typeof candidate.name === "string" && typeof candidate.email === "string";
}

function normalizeBuyer(buyer: BuyerProfile): BuyerProfile {
  return {
    name: buyer.name,
    email: buyer.email,
    phone: typeof buyer.phone === "string" ? buyer.phone : null,
    created_at: typeof buyer.created_at === "string" ? buyer.created_at : null,
  };
}

export function readBuyerSession(): BuyerSessionState | null {
  if (!isBrowser()) return null;
  try {
    const raw = window.localStorage.getItem(BUYER_STORAGE_KEY);
    if (!raw) return null;
    const parsed: unknown = JSON.parse(raw);
    if (typeof parsed !== "object" || parsed === null) return null;
    const session = parsed as Partial<BuyerSessionState>;
    if (!isValidBuyer(session.buyer)) return null;
    return { buyer: normalizeBuyer(session.buyer) };
  } catch {
    return null;
  }
}

/**
 * Migration douce (#8096) : lit le jeton d'une session LEGACY puis
 * réécrit immédiatement le stockage SANS le jeton (profil seul). Le jeton
 * retourné doit être échangé contre le cookie via `restoreBuyerSession`
 * puis oublié — il ne doit jamais être repersisté. Null si la session est
 * déjà au format courant ou absente.
 */
export function takeLegacyBuyerToken(): string | null {
  if (!isBrowser()) return null;
  try {
    const raw = window.localStorage.getItem(BUYER_STORAGE_KEY);
    if (!raw) return null;
    const parsed: unknown = JSON.parse(raw);
    if (typeof parsed !== "object" || parsed === null) return null;
    const legacy = parsed as Partial<LegacyBuyerSessionState>;
    if (typeof legacy.token !== "string" || legacy.token.length === 0) return null;

    // Purge immédiate du jeton : on conserve l'indice profil si valide.
    if (isValidBuyer(legacy.buyer)) {
      writeBuyerSession({ buyer: normalizeBuyer(legacy.buyer) });
    } else {
      clearBuyerSession();
    }
    return legacy.token;
  } catch {
    return null;
  }
}

export function writeBuyerSession(session: BuyerSessionState): void {
  if (!isBrowser()) return;
  try {
    window.localStorage.setItem(BUYER_STORAGE_KEY, JSON.stringify(session));
    window.dispatchEvent(new CustomEvent(BUYER_EVENT));
  } catch {
    // Stockage indisponible (navigation privée…) : session non persistée.
  }
}

export function clearBuyerSession(): void {
  if (!isBrowser()) return;
  try {
    window.localStorage.removeItem(BUYER_STORAGE_KEY);
    window.dispatchEvent(new CustomEvent(BUYER_EVENT));
  } catch {
    // Ignoré : rien à nettoyer.
  }
}
