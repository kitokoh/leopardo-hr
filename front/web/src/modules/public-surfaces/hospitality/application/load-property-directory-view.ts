/**
 * Cas d'usage — annuaire Hôtellerie.
 */

import {
  propertyDirectoryMeta,
  propertyKindLabel,
  type PropertyDirectoryEntry,
} from '../domain/property-directory';
import { loadPropertyDirectory } from '../infrastructure/property-gateway';
import { getSurfaceCopy } from '../../copy/surfaces-copy';

/** Entrée prête à l'affichage. */
export interface PropertyDirectoryCard {
  readonly entry: PropertyDirectoryEntry;
  readonly meta: string | null;
}

/** État de l'annuaire. */
export type PropertyDirectoryState = 'ready' | 'empty' | 'unavailable';

/** Résultat d'un chargement d'annuaire. */
export interface PropertyDirectoryResult {
  readonly state: PropertyDirectoryState;
  readonly cards: readonly PropertyDirectoryCard[];
}

/** Charge l'annuaire ; ne lève jamais. */
export async function loadPropertyDirectoryView(params: {
  q?: string;
  city?: string;
  page?: number;
  locale?: string;
}): Promise<PropertyDirectoryResult> {
  const result = await loadPropertyDirectory(params);

  if (!result.available) {
    return { state: 'unavailable', cards: [] };
  }

  if (result.entries.length === 0) {
    return { state: 'empty', cards: [] };
  }

  const kindLabels = getSurfaceCopy(params.locale).shared.propertyKinds;

  return {
    state: 'ready',
    cards: result.entries.map((entry) => {
      const kindLabel = propertyKindLabel(entry.kind, kindLabels);

      return { entry, meta: propertyDirectoryMeta(entry, kindLabel) };
    }),
  };
}
