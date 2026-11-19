/**
 * Domaine public — verticale Retail (Marché) : le produit.
 *
 * Repris de `front/marketplace` (#9627) lors de l'absorption de l'app
 * autonome. Un **produit** vu par le grand public : nom, prix, vendeur,
 * disponibilité. Aucune E/S, aucun import de framework.
 *
 * ## Ce que le domaine porte, et ce qu'il ne porte pas
 *
 * Il porte les **règles** : un prix inconnu ne s'affiche pas « 0 € », un
 * produit indisponible n'est pas commandable, un prix se formate en unité
 * mineure. Il ne porte **aucun texte** : les libellés viennent du catalogue de
 * copie (4 locales), comme partout dans `public-surfaces`.
 */

/** Un vendeur tel que le produit le référence. */
export interface ProductSeller {
  readonly slug: string;
  readonly name: string;
  readonly city: string | null;
}

/** Un produit du catalogue public. */
export interface Product {
  readonly id: number;
  readonly name: string;
  readonly description: string | null;
  readonly priceMinor: number;
  readonly currency: string;
  readonly imageUrl: string | null;
  readonly seller: ProductSeller;
  readonly available: boolean;
  readonly rating: number | null;
  readonly ratingCount: number;
}

/**
 * Formate un prix en unité mineure.
 *
 * `null` si le montant est inconnu ou négatif : on n'affiche jamais « 0 € »
 * pour un prix absent — c'est un mensonge commercial, pas un défaut d'UI.
 */
export function formatPrice(
  priceMinor: number | null,
  currency: string | null,
  locale = 'fr-FR',
): string | null {
  if (priceMinor === null || !Number.isFinite(priceMinor) || priceMinor < 0) {
    return null;
  }

  const amount = priceMinor / 100;

  try {
    return new Intl.NumberFormat(locale, {
      style: 'currency',
      currency: currency ?? 'EUR',
      maximumFractionDigits: 2,
    }).format(amount);
  } catch {
    // Devise inconnue d'Intl : on rend un montant lisible plutôt que de
    // laisser l'exception casser la page.
    return `${amount.toFixed(2)} ${currency ?? ''}`.trim();
  }
}

/**
 * Vrai si le produit peut être ajouté au panier.
 *
 * Règle du domaine : un produit sans prix connu n'est pas commandable — on ne
 * peut pas vendre ce qu'on ne sait pas facturer.
 */
export function isPurchasable(product: Product): boolean {
  return product.available && product.priceMinor >= 0 && product.priceMinor !== null;
}

/**
 * Note affichable : `null` sans avis.
 *
 * Même règle que `displayShopRating` : un produit sans avis n'affiche pas
 * « 0,0 », il n'affiche rien.
 */
export function displayProductRating(rating: number | null, ratingCount: number): number | null {
  if (rating === null || ratingCount <= 0) {
    return null;
  }

  return Math.round(rating * 10) / 10;
}
