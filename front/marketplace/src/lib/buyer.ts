/**
 * Session compte acheteur — Leopardo Marché (#7814, durcie par #8022).
 *
 * Depuis la tranche 2 de #8022 (suivi de #7979), le jeton opaque (`mkb_…`)
 * n'est PLUS stocké côté navigateur : l'API le pose en cookie
 * `HttpOnly; Secure; SameSite` (`market_buyer_token`) à l'inscription/à la
 * connexion, le navigateur le renvoie automatiquement (`credentials:
 * "include"` dans api.ts) et l'API l'accepte en repli du header
 * `Authorization: Bearer` (conservé pour les clients existants). Le JS de
 * la page ne voit donc jamais la credential — une XSS ne peut plus la
 * voler, et la CSP à nonce strict (tranche 1) réduit encore la surface.
 *
 * Seul le PROFIL public (nom, e-mail — non sensible) reste persisté en
 * localStorage (`leopardo_marche_buyer`) pour l'affichage (header, page
 * compte) : il n'est pas une credential. Même pattern que le panier :
 * store externe + CustomEvent pour synchroniser les composants du même
 * onglet, événement natif `storage` pour les autres onglets.
 *
 * Migration : les sessions héritées du format #7814 (qui contiennent un
 * `token` en clair) sont RÉÉCRITES sans le jeton à la première lecture —
 * la copie localStorage du token legacy disparaît donc dès le prochain
 * chargement de page. L'utilisateur devra se reconnecter si l'API ne
 * reconnaît pas de cookie (sessions pré-#8022) : purge silencieuse au
 * premier 401 (`handleUnauthorized`).
 */

import type { BuyerProfile } from "@/lib/api";

export const BUYER_STORAGE_KEY = "leopardo_marche_buyer";
export const BUYER_EVENT = "leopardo-marche:buyer";

/**
 * État de session côté navigateur : le profil public SEUL. Le jeton ne
 * transite plus jamais par ce module (#8022) — l'authentification des
 * requêtes est assurée par le cookie HttpOnly posé par l'API.
 */
export interface BuyerSessionState {
  buyer: BuyerProfile;
}

function isBrowser(): boolean {
  return typeof window !== "undefined";
}

function normalizeBuyer(raw: unknown): BuyerProfile | null {
  if (typeof raw !== "object" || raw === null) return null;
  const buyer = raw as Partial<BuyerProfile>;
  if (typeof buyer.name !== "string" || typeof buyer.email !== "string") {
    return null;
  }
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
    const buyer = normalizeBuyer((parsed as { buyer?: unknown }).buyer);
    if (buyer === null) return null;
    // #8022 — purge du jeton legacy : toute entrée contenant encore un
    // `token` (format #7814) est réécrite immédiatement sans lui.
    if (typeof (parsed as { token?: unknown }).token === "string") {
      window.localStorage.setItem(BUYER_STORAGE_KEY, JSON.stringify({ buyer }));
    }
    return { buyer };
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
