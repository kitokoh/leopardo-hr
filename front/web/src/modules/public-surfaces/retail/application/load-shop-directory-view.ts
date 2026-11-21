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
  /** Page courante (1-indexée) — nécessaire au rendu de la pagination. */
  readonly page: number;
  /** Dernière page connue de l'API. */
  readonly lastPage: number;
}

/** Charge l'annuaire ; ne lève jamais. */
export async function loadShopDirectoryView(params: {
  q?: string;
  city?: string;
  page?: number;
}): Promise<ShopDirectoryResult> {
  const result = await loadShopDirectory(params);
  const page = Math.max(1, params.page ?? 1);

  if (!result.available) {
    return { state: 'unavailable', cards: [], page, lastPage: 1 };
  }

  if (result.entries.length === 0) {
    return { state: 'empty', cards: [], page, lastPage: 1 };
  }

  return {
    state: 'ready',
    cards: result.entries.map((entry) => ({
      entry,
      meta: shopDirectoryMeta(entry),
    })),
    page,
    lastPage: result.lastPage,
  };
}
