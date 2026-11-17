/**
 * Domaine public — verticale Pharmacie.
 *
 * Une **officine** vue par le grand public : nom, ville, garde, services. Aucune
 * E/S.
 *
 * ## Note de périmètre
 *
 * Depuis #9592, la verticale Pharmacie a son provider de découverte public
 * (`PharmacyDiscoverableProvider`, tagué `public.discoverable`) et son opt-in
 * de publication (`pharmacy_public_profiles.enabled`, défaut `false`). La
 * vitrine est donc réellement alimentée : elle liste les officines qui ont
 * activé leur publication.
 *
 * L'état « bientôt disponible » reste possible et reste honnête : il signifie
 * qu'aucune officine n'a encore opté pour la publication, pas que la verticale
 * est cassée. `PharmacySurface` distingue explicitement `empty` (aucune
 * officine opt-in) de `unavailable` (panne API).
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
