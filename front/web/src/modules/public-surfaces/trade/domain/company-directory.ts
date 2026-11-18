/**
 * Domaine public — annuaire B2B (Entreprises).
 *
 * Une **entrée d'annuaire** est ce qu'un acheteur professionnel filtre : nom,
 * ville, catégories de produits, directions. Les directions sont des booléens
 * indépendants — une entreprise peut importer ET exporter.
 *
 * Aucune E/S.
 */

/** Une entreprise telle qu'elle apparaît dans l'annuaire. */
export interface CompanyDirectoryEntry {
  readonly slug: string;
  readonly name: string;
  readonly city: string | null;
  readonly description: string | null;
  readonly productCategories: readonly string[];
  readonly imports: boolean;
  readonly exports: boolean;
  readonly domestic: boolean;
}

/**
 * Directions déclarées, dans un ordre stable.
 *
 * Même règle que `tradeDirections` sur la fiche : l'ordre ne dépend pas de
 * l'ordre des booléens du DTO, deux rendus du même profil donnent la même liste.
 */
export function directoryDirections(
  entry: CompanyDirectoryEntry,
  labels: { importLabel: string; exportLabel: string; domesticLabel: string },
): readonly string[] {
  const directions: string[] = [];

  if (entry.imports) {
    directions.push(labels.importLabel);
  }

  if (entry.exports) {
    directions.push(labels.exportLabel);
  }

  if (entry.domestic) {
    directions.push(labels.domesticLabel);
  }

  return directions;
}

/**
 * Catégories affichables : au plus `limit`, sans doublon ni entrée vide.
 *
 * Une carte d'annuaire n'est pas une fiche : au-delà de trois catégories la
 * ligne devient illisible et pousse le nom hors du champ visuel.
 */
export function visibleCategories(
  entry: CompanyDirectoryEntry,
  limit = 3,
): readonly string[] {
  const seen = new Set<string>();
  const visible: string[] = [];

  for (const category of entry.productCategories) {
    const trimmed = category.trim();

    if (trimmed === '' || seen.has(trimmed)) {
      continue;
    }

    seen.add(trimmed);
    visible.push(trimmed);

    if (visible.length === limit) {
      break;
    }
  }

  return visible;
}
