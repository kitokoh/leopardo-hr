"use client";

/**
 * Hook panier — état React synchronisé avec localStorage via
 * useSyncExternalStore (le pattern recommandé pour un store externe) :
 * même onglet via le CustomEvent émis par writeCart, autres onglets via
 * l'événement natif `storage`.
 */

import { useCallback, useMemo, useSyncExternalStore } from "react";

import {
  CART_EVENT,
  CART_STORAGE_KEY,
  clearCart as clearCartStorage,
  countItems,
  groupBySeller,
  readCart,
  removeFromCart,
  removeSellerFromCart,
  updateQuantity,
  type CartGroup,
  type CartItem,
} from "@/lib/cart";

const EMPTY_CART: CartItem[] = [];

function subscribe(onStoreChange: () => void): () => void {
  const onStorage = (event: StorageEvent) => {
    if (event.key === null || event.key === CART_STORAGE_KEY) onStoreChange();
  };
  window.addEventListener(CART_EVENT, onStoreChange);
  window.addEventListener("storage", onStorage);
  return () => {
    window.removeEventListener(CART_EVENT, onStoreChange);
    window.removeEventListener("storage", onStorage);
  };
}

// Snapshot mis en cache : useSyncExternalStore exige une valeur stable tant
// que le contenu sérialisé n'a pas changé (sinon boucle de re-rendus).
let snapshotRaw: string | null = null;
let snapshotItems: CartItem[] = EMPTY_CART;

function getSnapshot(): CartItem[] {
  let raw: string | null = null;
  try {
    raw = window.localStorage.getItem(CART_STORAGE_KEY);
  } catch {
    raw = null;
  }
  if (raw !== snapshotRaw) {
    snapshotRaw = raw;
    snapshotItems = raw === null ? EMPTY_CART : readCart();
  }
  return snapshotItems;
}

function getServerSnapshot(): CartItem[] {
  return EMPTY_CART;
}

const subscribeNoop = () => () => {};

/** true après hydratation côté client (false pendant le rendu serveur). */
export function useHydrated(): boolean {
  return useSyncExternalStore(
    subscribeNoop,
    () => true,
    () => false,
  );
}

export interface UseCartResult {
  /** false tant que le client n'est pas hydraté (évite les décalages SSR). */
  ready: boolean;
  items: CartItem[];
  groups: CartGroup[];
  count: number;
  setQuantity: (productId: number, quantity: number) => void;
  remove: (productId: number) => void;
  removeSeller: (sellerSlug: string) => void;
  clear: () => void;
}

export function useCart(): UseCartResult {
  const ready = useHydrated();
  const items = useSyncExternalStore(subscribe, getSnapshot, getServerSnapshot);

  // Les mutations écrivent dans localStorage ; writeCart émet CART_EVENT,
  // ce qui invalide le snapshot ci-dessus — pas de setState manuel.
  const setQuantity = useCallback((productId: number, quantity: number) => {
    updateQuantity(productId, quantity);
  }, []);

  const remove = useCallback((productId: number) => {
    removeFromCart(productId);
  }, []);

  const removeSeller = useCallback((sellerSlug: string) => {
    removeSellerFromCart(sellerSlug);
  }, []);

  const clear = useCallback(() => {
    clearCartStorage();
  }, []);

  const groups = useMemo(() => groupBySeller(items), [items]);
  const count = useMemo(() => countItems(items), [items]);

  return { ready, items, groups, count, setQuantity, remove, removeSeller, clear };
}
