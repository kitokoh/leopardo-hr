/**
 * Domaine public — verticale Restaurant.
 *
 * ## Rôle
 *
 * Ce fichier décrit ce qu'est un **restaurant vu par le grand public** : son
 * nom, sa ville, sa cuisine, sa note, son ouverture. Il ne connaît ni HTTP, ni
 * Next, ni le client `restaurants-public-api` : la traduction depuis le contrat
 * API vit dans `infrastructure/`.
 *
 * ## Pourquoi un modèle propre plutôt que réutiliser le DTO API
 *
 * Le DTO public de l'API est un **contrat de transport** : il peut gagner un
 * champ interne demain sans que la vitrine doive changer. Le domaine, lui, ne
 * porte que ce que la vitrine affiche et ce sur quoi elle raisonne. Cette
 * séparation est ce qui permet à la vitrine d'avoir ses propres règles
 * (tri, ouverture, note) sans dépendre du shape réseau.
 */

/** Un restaurant tel que la vitrine le présente. */
export interface Restaurant {
  readonly slug: string;
  readonly name: string;
  /** Libellé lisible du type d'établissement (`null` si non renseigné). */
  readonly kind: string | null;
  readonly cuisines: readonly string[];
  readonly city: string | null;
  readonly description: string | null;
  readonly coverImageUrl: string | null;
  readonly rating: number | null;
  readonly reviewsCount: number;
  /** Distance en km — présent seulement en recherche par proximité. */
  readonly distanceKm: number | null;
}

/** Page de résultats de l'annuaire. */
export interface RestaurantPage {
  readonly items: readonly Restaurant[];
  readonly page: number;
  readonly lastPage: number;
  readonly total: number;
}

/** Critères de recherche acceptés par la vitrine. */
export interface RestaurantCriteria {
  readonly q?: string;
  readonly city?: string;
  readonly kind?: string;
  readonly cuisine?: string;
  readonly page?: number;
}

/**
 * Note affichable : `null` si aucun avis.
 *
 * Un restaurant sans avis ne doit pas afficher « 0,0 » — c'est un mensonge
 * visuel qui le fait passer pour mal noté. On renvoie `null` et l'UI masque
 * l'étoile.
 */
export function displayRating(rating: number | null, reviewsCount: number): number | null {
  if (rating === null || reviewsCount <= 0) {
    return null;
  }

  return Math.round(rating * 10) / 10;
}

/**
 * Libellé de distance, arrondi au dixième sous 10 km puis à l'entier.
 *
 * `null` quand la distance est inconnue : la vitrine n'invente pas de proximité.
 */
export function formatDistance(distanceKm: number | null): string | null {
  if (distanceKm === null || !Number.isFinite(distanceKm) || distanceKm < 0) {
    return null;
  }

  if (distanceKm < 10) {
    return `${distanceKm.toFixed(1)} km`;
  }

  return `${Math.round(distanceKm)} km`;
}

/**
 * Tri de l'annuaire : proximité d'abord si connue, puis note, puis nom.
 *
 * Règle du domaine, pas du composant : deux vitrines qui trieraient
 * différemment donneraient deux expériences incohérentes pour le même visiteur.
 */
export function sortRestaurants(items: readonly Restaurant[]): readonly Restaurant[] {
  return [...items].sort((a, b) => {
    const distanceA = a.distanceKm ?? Number.POSITIVE_INFINITY;
    const distanceB = b.distanceKm ?? Number.POSITIVE_INFINITY;

    if (distanceA !== distanceB) {
      return distanceA - distanceB;
    }

    const ratingA = a.rating ?? -1;
    const ratingB = b.rating ?? -1;

    if (ratingA !== ratingB) {
      return ratingB - ratingA;
    }

    return a.name.localeCompare(b.name);
  });
}

/** Vrai si la page ne contient aucun résultat. */
export function isEmptyPage(page: RestaurantPage): boolean {
  return page.items.length === 0;
}
