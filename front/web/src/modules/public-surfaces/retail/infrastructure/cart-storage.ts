/**
 * Persistance du panier — verticale Retail.
 *
 * Repris de `front/marketplace/src/lib/cart.ts` (#9627). Seule couche qui
 * touche `window` : le domaine reste pur, et cette frontière est ce qui rend
 * `cart.ts` testable sans navigateur.
 *
 * ## Clé de stockage conservée
 *
 * `leopardo_marche_cart` est **la même clé** que l'app d'origine. Un visiteur
 * qui avait un panier sur `front/marketplace` le retrouve ici : changer la clé
 * aurait silencieusement vidé les paniers en cours au moment de la bascule.
 */

import { sanitizeCartItem, type CartItem } from '../domain/cart';

export const CART_STORAGE_KEY = 'leopardo_marche_cart';
export const CART_EVENT = 'leopardo-marche:cart';

function isBrowser(): boolean {
  return typeof window !== 'undefined';
}

/** Lit le panier ; une entrée corrompue est écartée, jamais réparée. */
export function readCart(): CartItem[] {
  if (!isBrowser()) {
    return [];
  }

  try {
    const raw = window.localStorage.getItem(CART_STORAGE_KEY);

    if (!raw) {
      return [];
    }

    const parsed: unknown = JSON.parse(raw);

    if (!Array.isArray(parsed)) {
      return [];
    }

    return parsed.flatMap((entry) => {
      const item = sanitizeCartItem(entry);

      return item ? [item] : [];
    });
  } catch {
    return [];
  }
}

/**
 * Écrit le panier et notifie les composants du même onglet.
 *
 * L'événement natif `storage` ne se déclenche pas dans l'onglet qui écrit :
 * sans ce `CustomEvent`, le compteur du panier ne se mettrait pas à jour après
 * un ajout.
 */
export function writeCart(items: readonly CartItem[]): void {
  if (!isBrowser()) {
    return;
  }

  try {
    window.localStorage.setItem(CART_STORAGE_KEY, JSON.stringify(items));
  } catch {
    // Stockage plein ou désactivé : on n'interrompt pas la navigation.
  }

  window.dispatchEvent(new CustomEvent(CART_EVENT));
}

/** S'abonne aux changements de panier (même onglet + autres onglets). */
export function subscribeToCart(listener: () => void): () => void {
  if (!isBrowser()) {
    return () => {};
  }

  const onStorage = (event: StorageEvent): void => {
    if (event.key === CART_STORAGE_KEY) {
      listener();
    }
  };

  window.addEventListener(CART_EVENT, listener);
  window.addEventListener('storage', onStorage);

  return () => {
    window.removeEventListener(CART_EVENT, listener);
    window.removeEventListener('storage', onStorage);
  };
}
