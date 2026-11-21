/**
 * Adaptateur réseau — verticale Mobilité (VTC / taxi).
 *
 * Seule couture vers l'agrégateur public de découverte
 * (`@/lib/public-discovery-api`), avec le type canonique `vtc_operator` servi
 * par `VtcDiscoverableProvider` (#8773).
 *
 * ## Périmètre public réel
 *
 * Le provider borne l'annuaire aux sociétés ayant un opt-in
 * `vtc_marketplace_operators.status = active`, dont la feature `vtc` est active
 * et qui ne sont ni `suspended` ni `expired` (fail-closed). L'adaptateur ne
 * réimplémente donc **aucune** règle d'éligibilité : il ne fait que traduire.
 *
 * ## Ce qu'il ne fait pas
 *
 * Il ne devine rien. `category` est publiée à `null` par le provider (le type
 * canonique porte déjà le métier), et aucune note n'est exposée : la vitrine ne
 * fabrique donc ni catégorie ni score.
 */

import { searchPublicDiscovery } from '@/lib/public-discovery-api';

import type { MobilityOperator } from '../domain/operator';

/** Résultat de chargement : disponibilité explicite + opérateurs. */
export interface MobilityLoadResult {
  /** `false` si l'API n'a pas répondu (panne), distinct d'un annuaire vide. */
  readonly available: boolean;
  readonly operators: readonly MobilityOperator[];
  /** Dernière page connue de l'API (#9697). */
  readonly lastPage: number;
}

/**
 * Charge les opérateurs publics.
 *
 * `available: false` = panne API. `available: true` + liste vide = aucun
 * opérateur n'a encore activé son opt-in de marketplace.
 */
export async function loadOperators(params: {
  q?: string;
  city?: string;
  page?: number;
}): Promise<MobilityLoadResult> {
  const result = await searchPublicDiscovery({
    q: params.q,
    city: params.city,
    type: 'vtc_operator',
    page: params.page,
  });

  if (result === null) {
    return { available: false, operators: [], lastPage: 1 };
  }

  return {
    available: true,
    operators: result.items.map((item) => ({
      slug: item.slug,
      name: item.name,
      city: item.city ?? null,
      // Le DTO public porte `country` via le provider VTC, mais l'item agrégé
      // ne le normalise pas : on lit ce qui est réellement publié.
      country: null,
      currency: null,
    })),
    lastPage: Math.max(1, result.meta.last_page ?? 1),
  };
}
