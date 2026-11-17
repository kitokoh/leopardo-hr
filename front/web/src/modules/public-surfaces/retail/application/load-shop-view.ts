/**
 * Cas d'usage — verticale Retail.
 *
 * Charge une boutique et prépare son affichage (note normalisée, libellé de
 * catalogue). La page ne décide plus de ces règles.
 */

import {
  displayShopRating,
  formatCatalogSize,
  type Shop,
} from '../domain/shop';
import { loadShop } from '../infrastructure/shop-gateway';

/** Boutique prête à l'affichage. */
export interface ShopView {
  readonly shop: Shop;
  readonly rating: number | null;
  readonly catalogLabel: string;
}

/** Charge la vitrine d'une boutique ; `null` si introuvable ou API en panne. */
export async function loadShopView(slug: string): Promise<ShopView | null> {
  const shop = await loadShop(slug);

  if (shop === null) {
    return null;
  }

  return {
    shop,
    rating: displayShopRating(shop.rating, shop.ratingCount),
    catalogLabel: formatCatalogSize(shop.productsCount),
  };
}
