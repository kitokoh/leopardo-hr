import {
  DISCOVERY_CACHE_KEY,
  DISCOVERY_CACHE_SCHEMA,
  clearLastSearch,
  readLastSearch,
  saveLastSearch,
  type KeyValueStore,
} from '../public-discovery-cache';
import type { PublicDiscoveryItem, PublicDiscoveryMeta } from '@/lib/public-discovery-api';

/**
 * Couloir 16 (M4, #9106) — cache hors ligne de la dernière recherche publique.
 *
 * Critère d'acceptation : « cache de la dernière recherche et des favoris ;
 * écran hors ligne qui affiche le cache ». Ces tests verrouillent la
 * persistance, la tolérance aux pannes de stockage, et la règle de vie privée
 * (la position n'est jamais conservée).
 */

/** Faux `localStorage` : reproduit le vrai contrat, sans jsdom. */
class FakeStore implements KeyValueStore {
  public readonly data = new Map<string, string>();

  public failOnSet = false;

  public failOnGet = false;

  getItem(key: string): string | null {
    if (this.failOnGet) throw new Error('SecurityError');
    return this.data.get(key) ?? null;
  }

  setItem(key: string, value: string): void {
    if (this.failOnSet) throw new Error('QuotaExceededError');
    this.data.set(key, value);
  }

  removeItem(key: string): void {
    this.data.delete(key);
  }
}

const ITEM: PublicDiscoveryItem = {
  type: 'restaurant',
  slug: 'chez-fatou',
  name: 'Chez Fatou',
  city: 'Dakar',
  url: '/restaurants/chez-fatou',
};

const META: PublicDiscoveryMeta = {
  total: 1,
  per_page: 20,
  page: 1,
  last_page: 1,
  types: ['restaurant'],
  radius_km: null,
};

const QUERY = { q: 'coiffeur', city: 'Dakar', type: '', page: 1 };

describe('public-discovery-cache (M4 #9106)', () => {
  it('relit la dernière recherche après un rechargement', () => {
    const store = new FakeStore();

    expect(saveLastSearch(QUERY, [ITEM], META, store)).toBe(true);

    // Nouveau « chargement de page » : on relit depuis le même stockage.
    const restored = readLastSearch(store);

    expect(restored).not.toBeNull();
    expect(restored?.query).toEqual(QUERY);
    expect(restored?.items).toEqual([ITEM]);
    expect(restored?.meta).toEqual(META);
  });

  it('retourne null quand rien n’a été mis en cache', () => {
    expect(readLastSearch(new FakeStore())).toBeNull();
  });

  it('ne casse pas la page si le stockage est absent (SSR, navigation privée)', () => {
    expect(saveLastSearch(QUERY, [ITEM], META, null)).toBe(false);
    expect(readLastSearch(null)).toBeNull();
    expect(() => clearLastSearch(null)).not.toThrow();
  });

  it('ne casse pas la page si le quota est dépassé', () => {
    const store = new FakeStore();
    store.failOnSet = true;

    // L'écriture échoue proprement : la recherche n'est pas perdue pour autant.
    expect(saveLastSearch(QUERY, [ITEM], META, store)).toBe(false);
    expect(readLastSearch(store)).toBeNull();
  });

  it('ignore et purge une entrée d’un schéma antérieur', () => {
    const store = new FakeStore();
    store.setItem(
      DISCOVERY_CACHE_KEY,
      JSON.stringify({ schema: DISCOVERY_CACHE_SCHEMA - 1, query: QUERY, items: [ITEM] }),
    );

    expect(readLastSearch(store)).toBeNull();
    // Purgée : on ne retente pas de parser la même valeur invalide à chaque fois.
    expect(store.getItem(DISCOVERY_CACHE_KEY)).toBeNull();
  });

  it('ignore et purge une entrée corrompue', () => {
    const store = new FakeStore();
    store.setItem(DISCOVERY_CACHE_KEY, '{ pas du json');

    expect(readLastSearch(store)).toBeNull();
    expect(store.getItem(DISCOVERY_CACHE_KEY)).toBeNull();
  });

  it('ne conserve JAMAIS la position (vie privée)', () => {
    const store = new FakeStore();

    // La requête mise en cache ne porte pas de `near` : le type l'interdit, et
    // l'entrée persistée ne doit contenir aucune coordonnée.
    saveLastSearch(QUERY, [ITEM], META, store);

    const raw = store.getItem(DISCOVERY_CACHE_KEY) ?? '';
    expect(raw).not.toContain('near');
    expect(raw).not.toContain('latitude');
    expect(raw).not.toContain('longitude');
  });

  it('purge explicitement le cache', () => {
    const store = new FakeStore();
    saveLastSearch(QUERY, [ITEM], META, store);

    clearLastSearch(store);

    expect(readLastSearch(store)).toBeNull();
  });
});
