/**
 * Session compte acheteur — Leopardo Marché (#7814, migrée #8022).
 *
 * DEPUIS #8022 (suivi #7979, pattern #7841 de front/travel-web) : le jeton
 * opaque (`mkb_…`) ne transite PLUS JAMAIS par le JS de la page — il vit
 * dans un cookie httpOnly posé/supprimé par les route handlers Next
 * (`/api/v1/public/market/account/*`). Une XSS ne peut plus le voler.
 *
 * Ce module ne conserve en localStorage que le PROFIL PUBLIC (nom, email,
 * téléphone — pas une credential) pour un rendu instantané de l'état
 * connecté : clé `leopardo_marche_buyer_profile`. La vérité de session
 * reste le cookie, validé via `GET /account/me` au montage (useBuyer) ;
 * toute 401 purge le cache (handleUnauthorized).
 *
 * La clé historique `leopardo_marche_buyer` (qui contenait le JETON en
 * clair) est purgée au chargement du module : aucune copie du jeton ne
 * doit survivre dans un navigateur, même issue d'une session #7814.
 *
 * Même pattern que le panier : store externe + CustomEvent pour
 * synchroniser les composants du même onglet, événement natif `storage`
 * pour les autres onglets.
 */

import type { BuyerProfile } from "@/lib/api";

export const BUYER_STORAGE_KEY = "leopardo_marche_buyer_profile";
export const BUYER_EVENT = "leopardo-marche:buyer";

/** Ancienne clé localStorage contenant le jeton en clair (#7814) — purgée (#8022). */
const LEGACY_TOKEN_STORAGE_KEY = "leopardo_marche_buyer";

export interface BuyerSessionState {
  buyer: BuyerProfile;
}

function isBrowser(): boolean {
  return typeof window !== "undefined";
}

// Purge du jeton legacy AVANT tout : il ne doit plus exister de copie du
// jeton lisible par le JS (XSS), même issue d'une session #7814.
if (isBrowser()) {
  try {
    window.localStorage.removeItem(LEGACY_TOKEN_STORAGE_KEY);
  } catch {
    // Stockage indisponible (navigation privée…) : rien à purger.
  }
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
      typeof session.buyer !== "object" ||
      session.buyer === null ||
      typeof session.buyer.name !== "string" ||
      typeof session.buyer.email !== "string"
    ) {
      return null;
    }
    return {
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
    // Stockage indisponible (navigation privée…) : profil non persisté.
  }
}

export function clearBuyerSession(): void {
  if (!isBrowser()) return;
  try {
    window.localStorage.removeItem(BUYER_STORAGE_KEY);
    window.dispatchEvent(new CustomEvent(BUYER_EVENT));
  } catch {
    // Stockage indisponible : rien à purger.
  }
}
