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
}

/** Charge l'annuaire ; ne lève jamais. */
export async function loadCompanyDirectoryView(params: {
  q?: string;
  city?: string;
  page?: number;
  locale?: string;
}): Promise<CompanyDirectoryResult> {
  const result = await loadCompanyDirectory(params);

  if (!result.available) {
    return { state: 'unavailable', cards: [] };
  }

  if (result.entries.length === 0) {
    return { state: 'empty', cards: [] };
  }

  const shared = getSurfaceCopy(params.locale).shared;

  return {
    state: 'ready',
    cards: result.entries.map((entry) => ({
      entry,
      directions: directoryDirections(entry, shared),
      categories: visibleCategories(entry),
    })),
  };
}
