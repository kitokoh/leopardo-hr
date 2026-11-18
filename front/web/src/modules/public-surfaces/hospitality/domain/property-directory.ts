/**
 * Domaine public — annuaire Hôtellerie (Séjours).
 *
 * Une **entrée d'annuaire** est ce qu'un visiteur compare avant d'ouvrir une
 * fiche : nom, type d'hébergement, ville, classement. Le prix d'appel n'est
 * PAS dans l'entrée : l'agrégateur public ne le publie pas au niveau liste, et
 * l'inventer serait pire que de l'omettre.
 *
 * Aucune E/S.
 */

/** Un hébergement tel qu'il apparaît dans l'annuaire. */
export interface PropertyDirectoryEntry {
  readonly slug: string;
  readonly name: string;
  /** `hotel` | `residence` | `apartment_building` | `guesthouse`. */
  readonly kind: string | null;
  readonly city: string | null;
  readonly description: string | null;
}

/**
 * Libellé du type d'hébergement.
 *
 * Le code vient de l'API (`hotel`, `guesthouse`…) : on le traduit via le
 * catalogue, jamais en affichant le code brut. Un code inconnu retombe sur
 * `null` — la carte affiche alors la ville seule plutôt qu'un `apartment_building`
 * à l'écran.
 */
export function propertyKindLabel(
  kind: string | null,
  labels: Readonly<Record<string, string>>,
): string | null {
  if (kind === null) {
    return null;
  }

  return labels[kind] ?? null;
}

/** Métadonnées d'une carte : type · ville, sans séparateur orphelin. */
export function propertyDirectoryMeta(
  entry: PropertyDirectoryEntry,
  kindLabel: string | null,
): string | null {
  const parts = [kindLabel, entry.city].filter(
    (part): part is string => typeof part === 'string' && part.trim() !== '',
  );

  return parts.length === 0 ? null : parts.join(' · ');
}
