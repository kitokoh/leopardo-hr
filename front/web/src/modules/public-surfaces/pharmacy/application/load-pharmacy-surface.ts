/**
 * Cas d'usage — verticale Pharmacie.
 *
 * Charge les officines et prépare leur affichage. Distingue trois états :
 * panne API, verticale pas encore alimentée, et résultats réels.
 */

import { dutyLabel, type Pharmacy } from '../domain/pharmacy';
import { loadPharmacies } from '../infrastructure/pharmacy-gateway';

/** Officine prête à l'affichage. */
export interface PharmacyCard {
  readonly pharmacy: Pharmacy;
  readonly duty: string | null;
}

/** État de la vitrine pharmacie. */
export type PharmacySurfaceState = 'ready' | 'empty' | 'unavailable';

/** Résultat d'un chargement de vitrine pharmacie. */
export interface PharmacySurfaceResult {
  readonly state: PharmacySurfaceState;
  readonly cards: readonly PharmacyCard[];
}

/** Charge la vitrine pharmacie ; ne lève jamais. */
export async function loadPharmacySurface(params: {
  q?: string;
  city?: string;
  page?: number;
}): Promise<PharmacySurfaceResult> {
  const result = await loadPharmacies(params);

  if (!result.available) {
    return { state: 'unavailable', cards: [] };
  }

  if (result.pharmacies.length === 0) {
    return { state: 'empty', cards: [] };
  }

  return {
    state: 'ready',
    cards: result.pharmacies.map((pharmacy) => ({
      pharmacy,
      duty: dutyLabel(pharmacy.onDuty),
    })),
  };
}
