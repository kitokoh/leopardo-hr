/**
 * Cas d'usage — verticale Retail : le panier.
 *
 * Prépare l'affichage du panier (groupes par vendeur, sous-totaux formatés) et
 * expose les mutations. Le calcul reste dans le domaine ; ce fichier ne fait
 * que composer domaine + persistance.
 */

import { getSurfaceCopy } from '../../copy/surfaces-copy';
import {
  addItem,
  countItems,
  groupBySeller,
  removeItem,
  removeSeller,
  setQuantity,
  subtotalOf,
  type CartItem,
} from '../domain/cart';
import { formatPrice, type Product } from '../domain/product';
import { readCart, writeCart } from '../infrastructure/cart-storage';

/** Groupe de panier prêt à l'affichage. */
export interface CartGroupView {
  readonly sellerSlug: string;
  readonly sellerName: string;
  readonly sellerCity: string | null;
  readonly items: readonly CartItem[];
  readonly subtotalLabel: string | null;
}

/** Panier prêt à l'affichage. */
export interface CartView {
  readonly items: readonly CartItem[];
  readonly groups: readonly CartGroupView[];
  readonly itemCount: number;
  readonly totalLabel: string | null;
  readonly empty: boolean;
}

/** Compose l'affichage d'un panier. */
export function buildCartView(items: readonly CartItem[], locale = 'fr-FR'): CartView {
  const groups = groupBySeller(items);
  const currency = items[0]?.currency ?? 'EUR';

  return {
    items,
    groups: groups.map((group) => ({
      sellerSlug: group.seller.slug,
      sellerName: group.seller.name,
      sellerCity: group.seller.city,
      items: group.items,
      subtotalLabel: formatPrice(group.subtotalMinor, group.currency, locale),
    })),
    itemCount: countItems(items),
    totalLabel: formatPrice(subtotalOf(items), currency, locale),
    empty: items.length === 0,
  };
}

/** Lit le panier courant et le compose. */
export function loadCartView(locale = 'fr-FR'): CartView {
  return buildCartView(readCart(), locale);
}

/** Ajoute un produit et persiste. */
export function addProductToCart(product: Product, quantity: number): CartView {
  const next = addItem(readCart(), product, quantity);
  writeCart(next);

  return buildCartView(next);
}

/** Change une quantité et persiste. */
export function changeQuantity(productId: number, quantity: number): CartView {
  const next = setQuantity(readCart(), productId, quantity);
  writeCart(next);

  return buildCartView(next);
}

/** Retire une ligne et persiste. */
export function removeProduct(productId: number): CartView {
  const next = removeItem(readCart(), productId);
  writeCart(next);

  return buildCartView(next);
}

/** Vide le panier d'un vendeur et persiste. */
export function clearSeller(sellerSlug: string): CartView {
  const next = removeSeller(readCart(), sellerSlug);
  writeCart(next);

  return buildCartView(next);
}

/** Vide le panier entier. */
export function clearAll(): CartView {
  writeCart([]);

  return buildCartView([]);
}

/** Libellé « N articles », depuis le catalogue de copie. */
export function cartCountLabel(count: number, locale?: string): string {
  const shared = getSurfaceCopy(locale).shared;

  if (count <= 0) {
    return shared.catalogEmpty;
  }

  return `${count} ${count > 1 ? shared.products : shared.product}`;
}
