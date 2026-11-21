/**
 * Cas d'usage — verticale Mobilité (VTC / taxi).
 *
 * Charge les opérateurs et prépare leur affichage. Distingue trois états :
 * panne API, verticale pas encore alimentée, et résultats réels.
 *
 * ## Pourquoi trois états et pas deux
 *
 * `visible` (liste réelle) et `empty` (aucun opérateur opt-in) ne sont pas la
 * même chose pour le visiteur : la première appelle une action (choisir un
 * opérateur), la seconde est une information sur l'état de la plateforme. Les
 * confondre produirait l'état ambigu qui a déjà masqué deux verticales vides
 * (#9618). `unavailable` est une panne, et doit être annoncée comme telle.
 */

import { currencyLabel, operatorMeta, type MobilityOperator } from '../domain/operator';
import { loadOperators } from '../infrastructure/operator-gateway';

/** Opérateur prêt à l'affichage. */
export interface MobilityCard {
  readonly operator: MobilityOperator;
  /** Ville · pays, `null` si inconnu (jamais un séparateur orphelin). */
  readonly meta: string | null;
  /** Devise ISO à 3 lettres, `null` si absente ou malformée. */
  readonly currency: string | null;
}

/** État de la vitrine mobilité. */
export type MobilitySurfaceState = 'ready' | 'empty' | 'unavailable';

/** Résultat d'un chargement de vitrine mobilité. */
export interface MobilitySurfaceResult {
  readonly state: MobilitySurfaceState;
  readonly cards: readonly MobilityCard[];
  /** Page courante (1-indexée) — nécessaire au rendu de la pagination. */
  readonly page: number;
  /** Dernière page connue de l'API. */
  readonly lastPage: number;
}

/** Charge la vitrine mobilité ; ne lève jamais. */
export async function loadMobilitySurface(params: {
  q?: string;
  city?: string;
  page?: number;
}): Promise<MobilitySurfaceResult> {
  const result = await loadOperators(params);
  const page = Math.max(1, params.page ?? 1);

  if (!result.available) {
    return { state: 'unavailable', cards: [], page, lastPage: 1 };
  }

  if (result.operators.length === 0) {
    return { state: 'empty', cards: [], page, lastPage: 1 };
  }

  return {
    state: 'ready',
    cards: result.operators.map((operator) => ({
      operator,
      meta: operatorMeta(operator),
      currency: currencyLabel(operator),
    })),
    page,
    lastPage: result.lastPage,
  };
}
