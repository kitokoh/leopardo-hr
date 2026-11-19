/**
 * Domaine public — verticale Retail (Marché) : le panier.
 *
 * Repris de `front/marketplace/src/lib/cart.ts` (#9627). La logique de panier
 * était enfouie dans un module `lib/` de l'app autonome, mêlée à
 * `localStorage` et à `window`. Ici, la **règle** est pure et testable ; la
 * persistance vit dans `infrastructure/cart-storage.ts`.
 *
 * ## Règle structurante : 1 commande = 1 vendeur
 *
 * Côté API, une commande appartient à un seul vendeur. Le panier peut donc
 * contenir plusieurs vendeurs, mais il se **groupe** avant le checkout : c'est
 * `groupBySeller` qui produit les commandes à créer. Cette règle est portée par
 * le domaine, pas par la page de commande — sinon elle se réimplémente.
 */

import type { Product } from './product';

/** Quantité maximale par ligne — garde-fou anti-saisie aberrante. */
export const MAX_QUANTITY = 999;

/** Une ligne de panier. */
export interface CartItem {
  readonly productId: number;
  readonly name: string;
  readonly priceMinor: number;
  readonly currency: string;
  readonly imageUrl: string | null;
  readonly quantity: number;
  readonly seller: {
    readonly slug: string;
    readonly name: string;
    readonly city: string | null;
  };
}

/** Un groupe de lignes appartenant au même vendeur — l'unité de commande. */
export interface CartGroup {
  readonly seller: CartItem['seller'];
  readonly items: readonly CartItem[];
  readonly subtotalMinor: number;
  readonly currency: string;
}

/** Borne une quantité dans `[1, MAX_QUANTITY]`. */
export function clampQuantity(quantity: number): number {
  if (!Number.isFinite(quantity)) {
    return 1;
  }

  return Math.min(Math.max(Math.round(quantity), 1), MAX_QUANTITY);
}

/** Construit une ligne de panier depuis un produit. */
export function cartItemFromProduct(product: Product, quantity: number): CartItem {
  return {
    productId: product.id,
    name: product.name,
    priceMinor: product.priceMinor,
    currency: product.currency,
    imageUrl: product.imageUrl,
    quantity: clampQuantity(quantity),
    seller: {
      slug: product.seller.slug,
      name: product.seller.name,
      city: product.seller.city,
    },
  };
}

/**
 * Ajoute un produit au panier.
 *
 * Un produit déjà présent **cumule** sa quantité (bornée) au lieu de créer une
 * seconde ligne : deux lignes du même produit rendraient le sous-total par
 * vendeur faux à l'œil du client.
 */
export function addItem(items: readonly CartItem[], product: Product, quantity: number): CartItem[] {
  const existing = items.find((item) => item.productId === product.id);

  if (existing) {
    return items.map((item) =>
      item.productId === product.id
        ? { ...item, quantity: clampQuantity(item.quantity + quantity) }
        : item,
    );
  }

  return [...items, cartItemFromProduct(product, quantity)];
}

/** Change la quantité d'une ligne ; une quantité nulle retire la ligne. */
export function setQuantity(
  items: readonly CartItem[],
  productId: number,
  quantity: number,
): CartItem[] {
  if (quantity <= 0) {
    return removeItem(items, productId);
  }

  return items.map((item) =>
    item.productId === productId ? { ...item, quantity: clampQuantity(quantity) } : item,
  );
}

/** Retire une ligne. */
export function removeItem(items: readonly CartItem[], productId: number): CartItem[] {
  return items.filter((item) => item.productId !== productId);
}

/** Retire toutes les lignes d'un vendeur. */
export function removeSeller(items: readonly CartItem[], sellerSlug: string): CartItem[] {
  return items.filter((item) => item.seller.slug !== sellerSlug);
}

/** Nombre total d'articles (somme des quantités). */
export function countItems(items: readonly CartItem[]): number {
  return items.reduce((sum, item) => sum + item.quantity, 0);
}

/** Sous-total d'un ensemble de lignes. */
export function subtotalOf(items: readonly CartItem[]): number {
  return items.reduce((sum, item) => sum + item.priceMinor * item.quantity, 0);
}

/**
 * Groupe le panier par vendeur, dans l'ordre d'apparition.
 *
 * L'ordre est conservé volontairement : un panier qui se réordonne à chaque
 * rendu ferait perdre le repère au client.
 */
export function groupBySeller(items: readonly CartItem[]): CartGroup[] {
  const groups = new Map<string, { seller: CartItem['seller']; items: CartItem[] }>();

  for (const item of items) {
    const existing = groups.get(item.seller.slug);

    if (existing) {
      existing.items.push(item);
    } else {
      groups.set(item.seller.slug, { seller: item.seller, items: [item] });
    }
  }

  return [...groups.values()].map((group) => ({
    seller: group.seller,
    items: group.items,
    subtotalMinor: subtotalOf(group.items),
    currency: group.items[0]?.currency ?? 'EUR',
  }));
}

/**
 * Valide une ligne lue depuis le stockage.
 *
 * Le panier vit dans `localStorage` : il peut avoir été écrit par une version
 * antérieure, édité à la main, ou corrompu. Une ligne invalide est **écartée**,
 * jamais réparée avec des valeurs inventées.
 */
export function sanitizeCartItem(raw: unknown): CartItem | null {
  if (typeof raw !== 'object' || raw === null) {
    return null;
  }

  const item = raw as Partial<CartItem> & { seller?: Partial<CartItem['seller']> };

  if (
    typeof item.productId !== 'number' ||
    typeof item.name !== 'string' ||
    typeof item.priceMinor !== 'number' ||
    typeof item.currency !== 'string' ||
    typeof item.quantity !== 'number' ||
    typeof item.seller?.slug !== 'string' ||
    typeof item.seller?.name !== 'string'
  ) {
    return null;
  }

  return {
    productId: item.productId,
    name: item.name,
    priceMinor: item.priceMinor,
    currency: item.currency,
    imageUrl: typeof item.imageUrl === 'string' ? item.imageUrl : null,
    quantity: clampQuantity(item.quantity),
    seller: {
      slug: item.seller.slug,
      name: item.seller.name,
      city: typeof item.seller.city === 'string' ? item.seller.city : null,
    },
  };
}
