/**
 * Domaine public — verticale Pharmacie.
 *
 * Une **officine** vue par le grand public : nom, ville, garde, services. Aucune
 * E/S.
 *
 * ## Note de périmètre
 *
 * La verticale Pharmacie n'expose pas encore de provider de découverte public
 * (`PublicDiscoverableProvider`). La vitrine est donc **prête mais non
 * alimentée** : elle déclare son identité et son domaine, et son adaptateur
 * interroge l'agrégateur public de découverte. Tant qu'aucun provider
 * `pharmacy` n'est enregistré côté API, la vitrine affiche un état « bientôt
 * disponible » — jamais une liste vide présentée comme un résultat.
 */

/** Une officine telle que la vitrine la présente. */
export interface Pharmacy {
  readonly slug: string;
  readonly name: string;
  readonly city: string | null;
  readonly description: string | null;
  /** Vrai si l'officine est de garde (service continu). */
  readonly onDuty: boolean;
  readonly services: readonly string[];
}

/**
 * Libellé de garde : `null` si l'information n'est pas publiée.
 *
 * Règle du domaine : on n'écrit jamais « fermé » quand on ne sait pas —
 * l'absence d'information n'est pas une information. Les libellés viennent du
 * catalogue de copie (4 locales).
 */
export function dutyLabel(
  onDuty: boolean | null,
  words: { onDuty: string; regularHours: string },
): string | null {
  if (onDuty === null) {
    return null;
  }

  return onDuty ? words.onDuty : words.regularHours;
}

/** Vrai si l'officine publie au moins un service. */
export function hasServices(pharmacy: Pharmacy): boolean {
  return pharmacy.services.length > 0;
}
