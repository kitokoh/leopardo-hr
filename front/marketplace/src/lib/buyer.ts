/**
 * Session compte acheteur — Leopardo Marché (#7814).
 *
 * Le jeton opaque (`mkb_…`) et le profil public sont stockés en
 * localStorage (`leopardo_marche_buyer`). Même pattern que le panier :
 * store externe + CustomEvent pour synchroniser les composants du même
 * onglet, événement natif `storage` pour les autres onglets.
 *
 * ⚠️ COMPROMIS ASSUMÉ (#7979) : une credential en localStorage est lisible
 * par tout JS de la page (une XSS suffirait à la voler). La cible reste un
 * cookie HttpOnly; Secure; SameSite posé par l'API (échange cross-stack,
 * tranche dédiée). En attendant, la surface est réduite par :
 *   - CSP stricte posée par next.config (#7980) — script-src limité à
 *     'self' + inline du bootstrap, aucune origine tierce de script ;
 *   - TTL du jeton réduit à 7 jours (était 30) côté
 *     RetailBuyerAccountService (#7979) — rotation à chaque login ;
 *   - jeton opaque HASHÉ en base : sa valeur volée n'a d'usage que tant
 *     qu'il n'est ni expiré ni révoqué (logout = suppression).
 */

import type { BuyerProfile } from "@/lib/api";

export const BUYER_STORAGE_KEY = "leopardo_marche_buyer";
export const BUYER_EVENT = "leopardo-marche:buyer";

export interface BuyerSessionState {
  token: string;
  buyer: BuyerProfile;
}

function isBrowser(): boolean {
  return typeof window !== "undefined";
}

export function readBuyerSession(): BuyerSessionState | null {
  if (!isBrowser()) return null;
  try {
    const raw = window.localStorage.getItem(BUYER_STORAGE_KEY);
    if (!raw) return null;
    const parsed: unknown = JSON.parse(raw);
    if (typeof parsed !== "object" || parsed === null) return null;
    const session = parsed as Partial<BuyerSessionState>;
    if (
      typeof session.token !== "string" ||
      session.token.length === 0 ||
      typeof session.buyer !== "object" ||
      session.buyer === null ||
      typeof session.buyer.name !== "string" ||
      typeof session.buyer.email !== "string"
    ) {
      return null;
    }
    return {
      token: session.token,
      buyer: {
        name: session.buyer.name,
        email: session.buyer.email,
        phone: typeof session.buyer.phone === "string" ? session.buyer.phone : null,
        created_at: typeof session.buyer.created_at === "string" ? session.buyer.created_at : null,
      },
    };
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
