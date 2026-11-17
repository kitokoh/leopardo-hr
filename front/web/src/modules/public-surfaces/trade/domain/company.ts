/**
 * Domaine public — verticale B2B (Trade).
 *
 * Une **entreprise de commerce** vue par le grand public professionnel : nom,
 * ville/pays, catégories de produits, directions (import/export/domestique).
 * Aucune E/S.
 */

/** Une entreprise de commerce telle que la vitrine la présente. */
export interface Company {
  readonly slug: string;
  readonly name: string;
  readonly description: string | null;
  readonly city: string | null;
  readonly country: string | null;
  readonly productCategories: readonly string[];
  readonly currency: string | null;
  readonly imports: boolean;
  readonly exports: boolean;
  readonly domestic: boolean;
}

/**
 * Directions commerciales déclarées, dans un ordre stable.
 *
 * Règle du domaine : l'ordre d'affichage ne dépend pas de l'ordre des booléens
 * du DTO — deux rendus du même profil donnent la même liste. Les libellés
 * viennent du catalogue de copie (4 locales).
 */
export function tradeDirections(
  company: Company,
  labels: { importLabel: string; exportLabel: string; domesticLabel: string },
): readonly string[] {
  const directions: string[] = [];

  if (company.imports) {
    directions.push(labels.importLabel);
  }

  if (company.exports) {
    directions.push(labels.exportLabel);
  }

  if (company.domestic) {
    directions.push(labels.domesticLabel);
  }

  return directions;
}

/** Localisation lisible : « Lyon, France », ou la seule partie connue. */
export function companyLocation(company: Company): string | null {
  const parts = [company.city, company.country].filter(
    (part): part is string => typeof part === 'string' && part.trim() !== '',
  );

  return parts.length === 0 ? null : parts.join(', ');
}

/** Vrai si l'entreprise a publié au moins une catégorie de produits. */
export function hasProductCategories(company: Company): boolean {
  return company.productCategories.length > 0;
}
