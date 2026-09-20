/**
 * Panier — Leopardo Marché.
 *
 * Persistance localStorage (clé `leopardo_marche_cart`), groupement par
 * boutique (1 commande = 1 vendeur côté API). Chaque écriture émet un
 * CustomEvent pour synchroniser les composants du même onglet ; l'événement
 * natif `storage` couvre les autres onglets (cf. useCart).
 */

import type { PublicProduct } from "@/lib/api";

export const CART_STORAGE_KEY = "leopardo_marche_cart";
export const CART_EVENT = "leopardo-marche:cart";

export interface CartItem {
  productId: number;
  name: string;
  priceMinor: number;
  currency: string;
  imageUrl: string | null;
  quantity: number;
  seller: {
    slug: string;
    name: string;
    city: string | null;
  };
}

export interface CartGroup {
  seller: CartItem["seller"];
  items: CartItem[];
  subtotalMinor: number;
  currency: string;
}

export const MAX_QUANTITY = 999;

function isBrowser(): boolean {
  return typeof window !== "undefined";
}

function sanitizeItem(raw: unknown): CartItem | null {
  if (typeof raw !== "object" || raw === null) return null;
  const item = raw as Partial<CartItem> & { seller?: Partial<CartItem["seller"]> };
  if (
    typeof item.productId !== "number" ||
    typeof item.name !== "string" ||
    typeof item.priceMinor !== "number" ||
    typeof item.currency !== "string" ||
    typeof item.quantity !== "number" ||
    typeof item.seller?.slug !== "string" ||
    typeof item.seller?.name !== "string"
  ) {
    return null;
  }
  return {
    productId: item.productId,
    name: item.name,
    priceMinor: item.priceMinor,
    currency: item.currency,
    imageUrl: typeof item.imageUrl === "string" ? item.imageUrl : null,
    quantity: Math.min(Math.max(Math.round(item.quantity), 1), MAX_QUANTITY),
    seller: {
      slug: item.seller.slug,
      name: item.seller.name,
      city: typeof item.seller.city === "string" ? item.seller.city : null,
    },
  };
}

export function readCart(): CartItem[] {
  if (!isBrowser()) return [];
  try {
    const raw = window.localStorage.getItem(CART_STORAGE_KEY);
    if (!raw) return [];
    const parsed: unknown = JSON.parse(raw);
    if (!Array.isArray(parsed)) return [];
    return parsed.flatMap((entry) => {
      const item = sanitizeItem(entry);
      return item ? [item] : [];
    });
  } catch {
    return [];
  }
}

export function writeCart(items: CartItem[]): void {
  if (!isBrowser()) return;
  try {
    window.localStorage.setItem(CART_STORAGE_KEY, JSON.stringify(items));
  } catch {
    // Stockage plein ou indisponible : on n'interrompt pas la navigation.
  }
  window.dispatchEvent(new CustomEvent(CART_EVENT));
}

export function addToCart(product: PublicProduct, quantity: number): CartItem[] {
  const items = readCart();
  const existing = items.find((item) => item.productId === product.id);
  if (existing) {
    existing.quantity = Math.min(existing.quantity + quantity, MAX_QUANTITY);
  } else {
    items.push({
      productId: product.id,
      name: product.name,
      priceMinor: product.price_minor,
      currency: product.currency,
      imageUrl: product.image_url,
      quantity: Math.min(Math.max(Math.round(quantity), 1), MAX_QUANTITY),
      seller: {
        slug: product.seller.slug,
        name: product.seller.name,
        city: product.seller.city,
      },
    });
  }
  writeCart(items);
  return items;
}

export function updateQuantity(productId: number, quantity: number): CartItem[] {
  const items = readCart()
    .map((item) =>
      item.productId === productId
        ? { ...item, quantity: Math.min(Math.max(Math.round(quantity), 0), MAX_QUANTITY) }
        : item,
    )
    .filter((item) => item.quantity > 0);
  writeCart(items);
  return items;
}

export function removeFromCart(productId: number): CartItem[] {
  const items = readCart().filter((item) => item.productId !== productId);
  writeCart(items);
  return items;
}

export function removeSellerFromCart(sellerSlug: string): CartItem[] {
  const items = readCart().filter((item) => item.seller.slug !== sellerSlug);
  writeCart(items);
  return items;
}

export function clearCart(): void {
  writeCart([]);
}

export function countItems(items: CartItem[]): number {
  return items.reduce((sum, item) => sum + item.quantity, 0);
}

/** Groupe le panier par boutique — l'ordre d'apparition est conservé. */
export function groupBySeller(items: CartItem[]): CartGroup[] {
  const groups = new Map<string, CartGroup>();
  for (const item of items) {
    const existing = groups.get(item.seller.slug);
    if (existing) {
      existing.items.push(item);
      existing.subtotalMinor += item.priceMinor * item.quantity;
    } else {
      groups.set(item.seller.slug, {
        seller: item.seller,
        items: [item],
        subtotalMinor: item.priceMinor * item.quantity,
        currency: item.currency,
      });
    }
  }
  return [...groups.values()];
}
