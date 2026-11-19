/**
 * Infrastructure — verticale Retail : persistance de la session acheteur.
 *
 * ## Pourquoi ce fichier existe
 *
 * Ces fonctions vivaient dans `retail/domain/buyer.ts`, qui lisait et écrivait
 * `window.localStorage` et émettait un `CustomEvent`. Le domaine faisait donc
 * de l'E/S navigateur : il n'était pas testable sans DOM, et la règle de
 * couche n'était pas opposable (#9652).
 *
 * Le domaine garde ce qui est du domaine — la forme d'un profil, sa
 * validation, la règle d'avis vérifié. L'accès au stockage est ici.
 *
 * ## Ce que le client conserve — et ce qu'il ne conserve plus
 *
 * Depuis #8096, la session est portée par un cookie `HttpOnly; Secure;
 * SameSite=None` posé par l'API. **Le jeton n'est plus jamais écrit en
 * localStorage** : une XSS ne peut plus l'exfiltrer.
 *
 * Seul le **profil public** (nom, e-mail affichés à l'écran) reste stocké comme
 * indice d'affichage instantané. Ce n'est pas une credential : sans cookie
 * valide, l'API répond 401 et l'indice est purgé.
 *
 * ## Migration douce
 *
 * Les sessions legacy `{token, buyer}` sont détectées à la lecture : le jeton
 * est échangé **une fois** contre le cookie, puis purgé du stockage. Le jeton
 * rendu par `takeLegacyBuyerToken` ne doit jamais être repersisté.
 */

import {
  BUYER_EVENT,
  BUYER_STORAGE_KEY,
  isValidBuyer,
  normalizeBuyer,
  type BuyerProfile,
  type BuyerSession,
} from '../domain/buyer';

/** Forme legacy (avant #8096) : le jeton était persisté en clair. */
interface LegacyBuyerSession {
  readonly token: string;
  readonly buyer: BuyerProfile;
}

function isBrowser(): boolean {
  return typeof window !== 'undefined';
}

/** Lit la session locale ; `null` si absente ou illisible. */
export function readBuyerSession(): BuyerSession | null {
  if (!isBrowser()) {
    return null;
  }

  try {
    const raw = window.localStorage.getItem(BUYER_STORAGE_KEY);

    if (!raw) {
      return null;
    }

    const parsed: unknown = JSON.parse(raw);

    if (typeof parsed !== 'object' || parsed === null) {
      return null;
    }

    const session = parsed as Partial<BuyerSession>;

    return isValidBuyer(session.buyer) ? { buyer: normalizeBuyer(session.buyer) } : null;
  } catch {
    return null;
  }
}

/**
 * Extrait un jeton legacy et purge immédiatement le stockage.
 *
 * Le jeton rendu doit être échangé contre le cookie puis **oublié**. `null`
 * quand la session est déjà au format courant ou absente.
 */
export function takeLegacyBuyerToken(): string | null {
  if (!isBrowser()) {
    return null;
  }

  try {
    const raw = window.localStorage.getItem(BUYER_STORAGE_KEY);

    if (!raw) {
      return null;
    }

    const parsed: unknown = JSON.parse(raw);

    if (typeof parsed !== 'object' || parsed === null) {
      return null;
    }

    const legacy = parsed as Partial<LegacyBuyerSession>;

    if (typeof legacy.token !== 'string' || legacy.token.length === 0) {
      return null;
    }

    // Purge immédiate du jeton : on conserve l'indice profil s'il est valide.
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

/** Écrit la session locale et notifie les composants du même onglet. */
export function writeBuyerSession(session: BuyerSession): void {
  if (!isBrowser()) {
    return;
  }

  try {
    window.localStorage.setItem(BUYER_STORAGE_KEY, JSON.stringify(session));
    window.dispatchEvent(new CustomEvent(BUYER_EVENT));
  } catch {
    // Stockage indisponible (navigation privée) : session non persistée.
  }
}

/** Purge la session locale. */
export function clearBuyerSession(): void {
  if (!isBrowser()) {
    return;
  }

  try {
    window.localStorage.removeItem(BUYER_STORAGE_KEY);
    window.dispatchEvent(new CustomEvent(BUYER_EVENT));
  } catch {
    // Rien à nettoyer.
  }
}
