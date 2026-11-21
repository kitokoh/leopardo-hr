/**
 * Cas d'usage — verticale Pharmacie.
 *
 * Charge les officines et prépare leur affichage. Distingue trois états :
 * panne API, verticale pas encore alimentée, et résultats réels.
 */

import { dutyLabel, type Pharmacy } from '../domain/pharmacy';
import { loadPharmacies } from '../infrastructure/pharmacy-gateway';
import { getSurfaceCopy } from '../../copy/surfaces-copy';

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
  /** Page courante (1-indexée) — nécessaire au rendu de la pagination. */
  readonly page: number;
  /** Dernière page connue de l'API. */
  readonly lastPage: number;
}

/** Charge la vitrine pharmacie ; ne lève jamais. */
export async function loadPharmacySurface(params: {
  q?: string;
  city?: string;
  page?: number;
  locale?: string;
}): Promise<PharmacySurfaceResult> {
  const result = await loadPharmacies(params);
  const page = Math.max(1, params.page ?? 1);

  if (!result.available) {
    return { state: 'unavailable', cards: [], page, lastPage: 1 };
  }

  if (result.pharmacies.length === 0) {
    return { state: 'empty', cards: [], page, lastPage: 1 };
  }

  const shared = getSurfaceCopy(params.locale).shared;

  return {
    state: 'ready',
    cards: result.pharmacies.map((pharmacy) => ({
      pharmacy,
      duty: dutyLabel(pharmacy.onDuty, shared),
    })),
    page,
    lastPage: result.lastPage,
  };
}
