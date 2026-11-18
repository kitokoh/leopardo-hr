/**
 * Cas d'usage — annuaire Retail.
 *
 * Charge la liste des boutiques publiées et prépare leur affichage. Distingue
 * trois états, comme les autres vitrines : panne, annuaire vide, résultats.
 */

import { shopDirectoryMeta, type ShopDirectoryEntry } from '../domain/shop-directory';
import { loadShopDirectory } from '../infrastructure/shop-gateway';

/** Entrée prête à l'affichage. */
export interface ShopDirectoryCard {
  readonly entry: ShopDirectoryEntry;
  readonly meta: string | null;
}

/** État de l'annuaire. */
export type ShopDirectoryState = 'ready' | 'empty' | 'unavailable';

/** Résultat d'un chargement d'annuaire. */
export interface ShopDirectoryResult {
  readonly state: ShopDirectoryState;
  readonly cards: readonly ShopDirectoryCard[];
}

/** Charge l'annuaire ; ne lève jamais. */
export async function loadShopDirectoryView(params: {
  q?: string;
  city?: string;
  page?: number;
}): Promise<ShopDirectoryResult> {
  const result = await loadShopDirectory(params);

  if (!result.available) {
    return { state: 'unavailable', cards: [] };
  }

  if (result.entries.length === 0) {
    return { state: 'empty', cards: [] };
  }

  return {
    state: 'ready',
    cards: result.entries.map((entry) => ({
      entry,
      meta: shopDirectoryMeta(entry),
    })),
  };
}
