/**
 * Adaptateur réseau — verticale Pharmacie.
 *
 * Seule couture vers l'agrégateur public de découverte
 * (`@/lib/public-discovery-api`). Aucun provider `pharmacy` n'étant encore
 * enregistré côté API, la recherche renvoie une liste vide **légitime** : la
 * vitrine la distingue d'une panne via `available`.
 */

import { searchPublicDiscovery } from '@/lib/public-discovery-api';

import type { Pharmacy } from '../domain/pharmacy';

/** Résultat de chargement : disponibilité explicite + officines. */
export interface PharmacyLoadResult {
  /** `false` si l'API n'a pas répondu (panne), distinct d'un catalogue vide. */
  readonly available: boolean;
  readonly pharmacies: readonly Pharmacy[];
}

/**
 * Charge les officines publiques.
 *
 * `available: false` = panne API. `available: true` + liste vide = la verticale
 * n'expose encore aucune officine publique (état « bientôt disponible »).
 */
export async function loadPharmacies(params: {
  q?: string;
  city?: string;
  page?: number;
}): Promise<PharmacyLoadResult> {
  const result = await searchPublicDiscovery({
    q: params.q,
    city: params.city,
    type: 'pharmacy',
    page: params.page,
  });

  if (result === null) {
    return { available: false, pharmacies: [] };
  }

  return {
    available: true,
    pharmacies: result.items.map((item) => ({
      slug: item.slug,
      name: item.name,
      city: item.city ?? null,
      description: item.description ?? null,
      onDuty: false,
      services: [],
    })),
  };
}
