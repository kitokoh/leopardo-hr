/**
 * Cas d'usage — verticale Restaurant.
 *
 * Orchestre l'adaptateur et applique les règles du domaine (tri, note
 * affichable). La page ne fait plus que rendre : elle ne décide plus de l'ordre
 * des résultats ni de la façon de présenter une note absente.
 */

import {
  displayRating,
  sortRestaurants,
  type Restaurant,
  type RestaurantCriteria,
  type RestaurantPage,
} from '../domain/restaurant';
import { loadRestaurantPage } from '../infrastructure/restaurant-gateway';

/** Restaurant prêt à l'affichage (note déjà normalisée). */
export interface RestaurantCard {
  readonly restaurant: Restaurant;
  readonly rating: number | null;
}

/** Résultat d'un chargement de vitrine. */
export interface RestaurantSurfaceResult {
  readonly cards: readonly RestaurantCard[];
  readonly page: number;
  readonly lastPage: number;
  readonly total: number;
  /** `true` si l'API publique n'a pas répondu. */
  readonly failed: boolean;
}

/**
 * Charge la vitrine restaurant pour des critères donnés.
 *
 * Ne lève jamais : une panne API devient `failed: true` avec une liste vide,
 * que la page traduit en message d'erreur — jamais en « aucun résultat ».
 */
export async function loadRestaurantSurface(
  criteria: RestaurantCriteria,
): Promise<RestaurantSurfaceResult> {
  const page: RestaurantPage | null = await loadRestaurantPage(criteria);

  if (page === null) {
    return { cards: [], page: 1, lastPage: 1, total: 0, failed: true };
  }

  const cards = sortRestaurants(page.items).map((restaurant) => ({
    restaurant,
    rating: displayRating(restaurant.rating, restaurant.reviewsCount),
  }));

  return {
    cards,
    page: page.page,
    lastPage: page.lastPage,
    total: page.total,
    failed: false,
  };
}
