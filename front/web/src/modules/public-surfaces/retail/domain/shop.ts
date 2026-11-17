/**
 * Domaine public — verticale Retail (Marché).
 *
 * Une **boutique** vue par le grand public : son nom, sa ville, son catalogue
 * (nombre de produits, catégories), sa note. Aucune E/S, aucun import de
 * framework : la traduction depuis le contrat public vit dans `infrastructure/`.
 */

/** Une boutique telle que la vitrine la présente. */
export interface Shop {
  readonly slug: string;
  readonly name: string;
  readonly city: string | null;
  readonly description: string | null;
  readonly currency: string | null;
  readonly productsCount: number;
  readonly rating: number | null;
  readonly ratingCount: number;
  readonly categories: readonly ShopCategory[];
}

/** Catégorie publique d'une boutique (nom + comptage, jamais d'id interne). */
export interface ShopCategory {
  readonly name: string;
  readonly productsCount: number;
}

/**
 * Note affichable : `null` sans avis.
 *
 * Une boutique sans avis ne doit pas afficher « 0,0 » — cela la ferait passer
 * pour mal notée alors qu'elle n'a simplement pas encore été évaluée.
 */
export function displayShopRating(rating: number | null, ratingCount: number): number | null {
  if (rating === null || ratingCount <= 0) {
    return null;
  }

  return Math.round(rating * 10) / 10;
}

/**
 * Libellé du catalogue : « 12 produits » / « 1 produit » / « Catalogue vide ».
 *
 * Règle du domaine : le pluriel et le cas vide sont décidés une fois, pas dans
 * chaque composant.
 */
export function formatCatalogSize(productsCount: number): string {
  if (productsCount <= 0) {
    return 'Catalogue vide';
  }

  return `${productsCount} produit${productsCount > 1 ? 's' : ''}`;
}

/** Vrai si la boutique expose au moins une catégorie. */
export function hasCategories(shop: Shop): boolean {
  return shop.categories.length > 0;
}
