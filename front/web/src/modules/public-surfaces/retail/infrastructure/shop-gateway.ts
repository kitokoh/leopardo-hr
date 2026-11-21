/**
 * Adaptateur réseau — verticale Retail.
 *
 * Seule couture vers `@/lib/retail-public-api`. Mapping explicite champ par
 * champ : un renommage côté API casse ici, pas dans le rendu.
 */

import { getPublicShop, type PublicShopProfile } from '@/lib/retail-public-api';
import { searchPublicDiscovery } from '@/lib/public-discovery-api';

import type { Shop } from '../domain/shop';
import type { ShopDirectoryEntry } from '../domain/shop-directory';

/** Résultat d'un chargement d'annuaire : disponibilité explicite + entrées. */
export interface ShopDirectoryLoadResult {
  /** `false` si l'API n'a pas répondu (panne), distinct d'un annuaire vide. */
  readonly available: boolean;
  readonly entries: readonly ShopDirectoryEntry[];
  /**
   * Dernière page connue de l'API (#9657).
   *
   * L'agrégateur public renvoie déjà `meta.last_page` ; sans le propager, la
   * vitrine ne pouvait pas savoir qu'il existait d'autres pages et n'affichait
   * aucun contrôle de pagination.
   */
  readonly lastPage: number;
}

/**
 * Charge l'annuaire des boutiques publiées (#9618).
 *
 * Passe par l'agrégateur public (`?type=retail_shop`) : même contrat que les
 * fiches, donc aucune divergence entre la liste et le détail.
 */
export async function loadShopDirectory(params: {
  q?: string;
  city?: string;
  page?: number;
}): Promise<ShopDirectoryLoadResult> {
  const result = await searchPublicDiscovery({
    q: params.q,
    city: params.city,
    type: 'retail_shop',
    page: params.page,
  });

  if (result === null) {
    return { available: false, entries: [], lastPage: 1 };
  }

  return {
    available: true,
    entries: result.items.map((item) => ({
      slug: item.slug,
      name: item.name,
      city: item.city ?? null,
      description: item.description ?? null,
      category: item.category ?? null,
    })),
    lastPage: Math.max(1, result.meta.last_page ?? 1),
  };
}

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
