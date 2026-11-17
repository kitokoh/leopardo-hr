/**
 * Adaptateur réseau — verticale Retail.
 *
 * Seule couture vers `@/lib/retail-public-api`. Mapping explicite champ par
 * champ : un renommage côté API casse ici, pas dans le rendu.
 */

import { getPublicShop, type PublicShopProfile } from '@/lib/retail-public-api';

import type { Shop } from '../domain/shop';

/** Traduit la fiche publique d'une boutique vers le modèle de la vitrine. */
export function toShop(profile: PublicShopProfile): Shop {
  return {
    slug: profile.slug,
    name: profile.name,
    city: profile.city,
    description: profile.description,
    currency: profile.currency,
    productsCount: profile.products_count,
    rating: profile.rating_avg,
    ratingCount: profile.rating_count,
    categories: profile.categories.map((category) => ({
      name: category.name,
      productsCount: category.products_count,
    })),
  };
}

/**
 * Charge une boutique par slug.
 *
 * `null` = 404 fail-closed ou API indisponible : la page répond `notFound()`.
 * On ne fabrique jamais une boutique vide.
 */
export async function loadShop(slug: string): Promise<Shop | null> {
  const profile = await getPublicShop(slug);

  return profile === null ? null : toShop(profile);
}
