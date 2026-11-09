/**
 * Couloir 16 (M4, #9106) — cache hors ligne de la dernière recherche publique.
 *
 * ## Le trou que ce module ferme
 *
 * Le cahier des charges de M4 exige : « cache de la dernière recherche et des
 * favoris ; écran hors ligne qui affiche le cache ». Or la passerelle publique
 * `/decouvrir` n'avait **aucun** cache : `DiscoveryExplorer.fetchResults()`
 * appelait `apiFetch('/public/discover')` et, en cas d'échec, basculait
 * directement sur l'état d'erreur. Un visiteur qui avait cherché « coiffeur »
 * puis perdait le réseau voyait « Impossible de charger les résultats » — alors
 * que la réponse précédente était encore en mémoire.
 *
 * ## Ce que ce module fait — et ne fait pas
 *
 * Il persiste **la dernière recherche réussie** (requête + résultats + méta)
 * dans `localStorage`, et la restitue quand le réseau est indisponible.
 *
 * Il ne fait **pas** de synchronisation différée : une recherche est une
 * LECTURE, pas une écriture. Il n'y a rien à rejouer au retour du réseau — donc
 * pas de file d'attente ni de clé d'idempotence ici (contrairement à la file
 * des messages, qui, elle, porte des écritures). Confondre les deux ferait
 * naître une file sans objet.
 *
 * ## Règles de sûreté
 *
 *  - **Aucune donnée personnelle** : le cache ne contient que ce que l'API
 *    publique renvoie déjà sans authentification (DTO public strict). La
 *    position (`near`) n'est **jamais** mise en cache — c'est une donnée
 *    volatile, et le cahier des charges impose qu'elle ne soit pas conservée.
 *  - **Tolérant** : `localStorage` peut être absent (SSR, navigation privée
 *    stricte), plein (`QuotaExceededError`) ou corrompu (schéma d'une autre
 *    version). Aucun de ces cas ne doit casser la page : on dégrade vers
 *    « pas de cache » et on le dit, plutôt que de planter.
 *  - **Versionné** : une entrée d'une version antérieure du schéma est ignorée,
 *    jamais interprétée de travers.
 *  - **Testable en Node** : le `Storage` est injectable, aucune dépendance DOM.
 */

import type { PublicDiscoveryItem, PublicDiscoveryMeta } from '@/lib/public-discovery-api';

/** Sous-ensemble de l'API Web Storage réellement utilisé. */
export interface KeyValueStore {
  getItem(key: string): string | null;
  setItem(key: string, value: string): void;
  removeItem(key: string): void;
}

/** Clé de stockage. Versionnée : un changement de schéma invalide l'ancienne. */
export const DISCOVERY_CACHE_KEY = 'leopardo.public.discovery.last-search.v1';

/** Version du schéma de l'entrée persistée. */
export const DISCOVERY_CACHE_SCHEMA = 1;

/** Requête mise en cache (jamais `near` : donnée volatile, non conservée). */
export interface CachedDiscoveryQuery {
  q: string;
  city: string;
  type: string;
  page: number;
}

export interface CachedDiscovery {
  schema: number;
  savedAt: number;
  query: CachedDiscoveryQuery;
  items: PublicDiscoveryItem[];
  meta: PublicDiscoveryMeta | null;
}

/**
 * Résout le `Storage` par défaut, ou `null` si indisponible.
 *
 * `localStorage` peut lever à l'ACCÈS même (Safari en navigation privée
 * stricte) : le `try` couvre donc la lecture de la propriété, pas seulement
 * l'écriture.
 */
export function defaultStore(): KeyValueStore | null {
  try {
    if (typeof globalThis === 'undefined') return null;
    const store = (globalThis as { localStorage?: KeyValueStore }).localStorage;
    return store ?? null;
  } catch {
    return null;
  }
}

/**
 * Écrit la dernière recherche réussie.
 *
 * Ne persiste **jamais** une recherche par proximité (`near` non nul) : la
 * position ne doit pas survivre à la session. Retourne `true` si l'écriture a
 * abouti, `false` sinon (quota, stockage absent) — l'appelant peut l'ignorer,
 * un cache manquant n'est pas une erreur utilisateur.
 */
export function saveLastSearch(
  query: CachedDiscoveryQuery,
  items: PublicDiscoveryItem[],
  meta: PublicDiscoveryMeta | null,
  store: KeyValueStore | null = defaultStore(),
  now: number = Date.now(),
): boolean {
  if (!store) return false;

  const entry: CachedDiscovery = {
    schema: DISCOVERY_CACHE_SCHEMA,
    savedAt: now,
    query,
    items,
    meta,
  };

  try {
    store.setItem(DISCOVERY_CACHE_KEY, JSON.stringify(entry));
    return true;
  } catch {
    // Quota dépassé ou stockage refusé : on n'échoue pas la recherche pour
    // autant. Le cache est un confort, pas une condition de fonctionnement.
    return false;
  }
}

/**
 * Relit la dernière recherche, ou `null` si absente / illisible / d'un schéma
 * antérieur.
 *
 * Une entrée corrompue est **supprimée** plutôt que laissée en place : sans
 * cela, chaque chargement retenterait de parser la même valeur invalide.
 */
export function readLastSearch(
  store: KeyValueStore | null = defaultStore(),
): CachedDiscovery | null {
  if (!store) return null;

  let raw: string | null;
  try {
    raw = store.getItem(DISCOVERY_CACHE_KEY);
  } catch {
    return null;
  }
  if (!raw) return null;

  try {
    const parsed = JSON.parse(raw) as Partial<CachedDiscovery>;

    if (
      parsed.schema !== DISCOVERY_CACHE_SCHEMA ||
      !parsed.query ||
      !Array.isArray(parsed.items)
    ) {
      // Schéma inconnu ou forme invalide : on purge au lieu de deviner.
      try {
        store.removeItem(DISCOVERY_CACHE_KEY);
      } catch {
        /* purge best-effort */
      }
      return null;
    }

    return {
      schema: DISCOVERY_CACHE_SCHEMA,
      savedAt: typeof parsed.savedAt === 'number' ? parsed.savedAt : 0,
      query: parsed.query,
      items: parsed.items,
      meta: parsed.meta ?? null,
    };
  } catch {
    try {
      store.removeItem(DISCOVERY_CACHE_KEY);
    } catch {
      /* purge best-effort */
    }
    return null;
  }
}

/** Purge explicite (déconnexion, effacement des données). */
export function clearLastSearch(store: KeyValueStore | null = defaultStore()): void {
  if (!store) return;
  try {
    store.removeItem(DISCOVERY_CACHE_KEY);
  } catch {
    /* purge best-effort */
  }
}
