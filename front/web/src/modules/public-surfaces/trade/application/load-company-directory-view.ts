/**
 * Cas d'usage — annuaire B2B.
 */

import {
  directoryDirections,
  visibleCategories,
  type CompanyDirectoryEntry,
} from '../domain/company-directory';
import { loadCompanyDirectory } from '../infrastructure/company-gateway';
import { getSurfaceCopy } from '../../copy/surfaces-copy';

/** Entrée prête à l'affichage. */
export interface CompanyDirectoryCard {
  readonly entry: CompanyDirectoryEntry;
  readonly directions: readonly string[];
  readonly categories: readonly string[];
}

/** État de l'annuaire. */
export type CompanyDirectoryState = 'ready' | 'empty' | 'unavailable';

/** Résultat d'un chargement d'annuaire. */
export interface CompanyDirectoryResult {
  readonly state: CompanyDirectoryState;
  readonly cards: readonly CompanyDirectoryCard[];
  /** Page courante (1-indexée) — nécessaire au rendu de la pagination. */
  readonly page: number;
  /** Dernière page connue de l'API. */
  readonly lastPage: number;
}

/** Charge l'annuaire ; ne lève jamais. */
export async function loadCompanyDirectoryView(params: {
  q?: string;
  city?: string;
  page?: number;
  locale?: string;
}): Promise<CompanyDirectoryResult> {
  const result = await loadCompanyDirectory(params);
  const page = Math.max(1, params.page ?? 1);

  if (!result.available) {
    return { state: 'unavailable', cards: [], page, lastPage: 1 };
  }

  if (result.entries.length === 0) {
    return { state: 'empty', cards: [], page, lastPage: 1 };
  }

  const shared = getSurfaceCopy(params.locale).shared;

  return {
    state: 'ready',
    cards: result.entries.map((entry) => ({
      entry,
      directions: directoryDirections(entry, shared),
      categories: visibleCategories(entry),
    })),
    page,
    lastPage: result.lastPage,
  };
}
