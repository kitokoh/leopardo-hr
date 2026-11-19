/**
 * Cas d'usage — verticale Retail : le catalogue.
 *
 * Charge les produits et prépare leur affichage (prix formaté, note
 * affichable, achat possible). Ne lève jamais : une panne devient un état
 * explicite, pas une exception qui casse la page.
 */

import { getSurfaceCopy } from '../../copy/surfaces-copy';
import { displayProductRating, formatPrice, isPurchasable, type Product } from '../domain/product';
import { searchProducts } from '../infrastructure/market-gateway';

/** Produit prêt à l'affichage. */
export interface ProductCard {
  readonly product: Product;
  readonly priceLabel: string | null;
  readonly rating: number | null;
  readonly purchasable: boolean;
}

/** Résultat d'un chargement de catalogue. */
export interface CatalogResult {
  readonly cards: readonly ProductCard[];
  /** `true` si l'API publique n'a pas répondu. */
  readonly failed: boolean;
  readonly page: number;
  readonly lastPage: number;
  readonly total: number;
}

/** Charge le catalogue public ; ne lève jamais. */
export async function loadCatalog(params: {
  q?: string;
  seller?: string;
  category?: string;
  page?: number;
  locale?: string;
}): Promise<CatalogResult> {
  const result = await searchProducts(params);

  if (result === null) {
    return { cards: [], failed: true, page: 1, lastPage: 1, total: 0 };
  }

  const locale = params.locale ?? 'fr-FR';

  return {
    cards: result.items.map((product) => ({
      product,
      priceLabel: formatPrice(product.priceMinor, product.currency, locale),
      rating: displayProductRating(product.rating, product.ratingCount),
      purchasable: isPurchasable(product),
    })),
    failed: false,
    page: result.page,
    lastPage: result.lastPage,
    total: result.total,
  };
}

/** Charge une fiche produit ; `null` si absente ou API indisponible. */
export async function loadProductView(
  id: string | number,
  locale = 'fr-FR',
): Promise<ProductCard | null> {
  const { loadProduct } = await import('../infrastructure/market-gateway');
  const product = await loadProduct(id);

  if (product === null) {
    return null;
  }

  return {
    product,
    priceLabel: formatPrice(product.priceMinor, product.currency, locale),
    rating: displayProductRating(product.rating, product.ratingCount),
    purchasable: isPurchasable(product),
  };
}

/** Libellé de disponibilité, depuis le catalogue de copie. */
export function availabilityLabel(purchasable: boolean, locale?: string): string {
  const shared = getSurfaceCopy(locale).shared;

  return purchasable ? shared.bookable : shared.soldOut;
}
