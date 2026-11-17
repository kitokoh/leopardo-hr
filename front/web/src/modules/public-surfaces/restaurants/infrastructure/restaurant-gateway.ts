/**
 * Adaptateur réseau — verticale Restaurant.
 *
 * **Seule** couture entre le domaine de la vitrine et le contrat public
 * existant `@/lib/restaurants-public-api`. Aucune route API n'est inventée : on
 * traduit le DTO de transport vers le modèle du domaine.
 *
 * Le mapping est explicite (champ par champ) plutôt qu'un `as` : si l'API
 * renomme un champ, la compilation casse ici — au bon endroit — au lieu de
 * produire un `undefined` silencieux dans le rendu.
 */

import {
  searchPublicRestaurants,
  type PublicRestaurantSummary,
} from '@/lib/restaurants-public-api';

import type { Restaurant, RestaurantCriteria, RestaurantPage } from '../domain/restaurant';

/** Traduit un item public de l'API vers le modèle de la vitrine. */
export function toRestaurant(item: PublicRestaurantSummary): Restaurant {
  return {
    slug: item.slug,
    name: item.name,
    kind: item.establishment_type,
    cuisines: item.cuisine_types ?? [],
    city: item.city,
    description: item.description,
    coverImageUrl: item.cover_image_url,
    rating: item.rating_avg,
    reviewsCount: item.reviews_count,
    distanceKm: item.distance_km ?? null,
  };
}

/**
 * Charge une page de l'annuaire public.
 *
 * `null` = API indisponible ou erreur : la page affiche un état d'erreur
 * explicite. On ne renvoie **jamais** une page vide qui laisserait croire qu'il
 * n'existe aucun restaurant.
 */
export async function loadRestaurantPage(
  criteria: RestaurantCriteria,
): Promise<RestaurantPage | null> {
  const result = await searchPublicRestaurants({
    q: criteria.q,
    city: criteria.city,
    type: criteria.kind,
    cuisine: criteria.cuisine,
    page: criteria.page,
  });

  if (result === null) {
    return null;
  }

  return {
    items: result.items.map(toRestaurant),
    page: result.meta.current_page,
    lastPage: result.meta.last_page,
    total: result.meta.total,
  };
}
