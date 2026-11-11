/**
 * Couloir 16 (M1) — gardes du client de catégories publiques.
 *
 * Ces tests protègent trois décisions qui, si elles régressaient, feraient
 * apparaître des catégories vides ou non publiées sur la home :
 *
 *  1. `visible` est fail-closed (absent ≠ vrai) ;
 *  2. `selectDisplayableCategories` exige AUSSI `count > 0` ;
 *  3. une panne réseau rend `null`, pas une liste vide — la distinction
 *     permet d'afficher un état dégradé honnête.
 */
import { jest } from '@jest/globals';

jest.doMock('@/lib/backend-url', () => ({
  resolveBackendBaseUrl: () => 'https://api.test/api/v1',
}));

const originalFetch = global.fetch;

afterEach(() => {
  global.fetch = originalFetch;
});

function mockFetch(payload: unknown, ok = true, status = 200) {
  global.fetch = jest.fn(async () => ({
    ok,
    status,
    json: async () => payload,
  })) as unknown as typeof fetch;
}

describe('getPublicDiscoveryCategories', () => {
  it('normalise les entrées et conserve le seuil du backend', async () => {
    mockFetch({
      data: [
        { key: 'restaurant', label: 'Restaurants', count: 12, visible: true },
        { key: 'garage', label: 'Garages', count: 2, visible: false },
      ],
      meta: { threshold: 3, city: 'Alger' },
    });

    const { getPublicDiscoveryCategories } = await import('../data/discovery-categories');
    const result = await getPublicDiscoveryCategories('Alger');

    expect(result).not.toBeNull();
    expect(result?.categories).toHaveLength(2);
    expect(result?.meta).toEqual({ threshold: 3, city: 'Alger' });
  });

  it('fail-closed : `visible` absent ou non booléen ⇒ false', async () => {
    mockFetch({
      data: [
        { key: 'a', label: 'A', count: 9 },
        { key: 'b', label: 'B', count: 9, visible: 'true' },
        { key: 'c', label: 'C', count: 9, visible: true },
      ],
      meta: { threshold: 1, city: null },
    });

    const { getPublicDiscoveryCategories } = await import('../data/discovery-categories');
    const result = await getPublicDiscoveryCategories();

    expect(result?.categories.map((c) => c.visible)).toEqual([false, false, true]);
  });

  it('écarte les entrées sans clé exploitable', async () => {
    mockFetch({
      data: [{ key: '', count: 4, visible: true }, { count: 4, visible: true }, null, 'x'],
      meta: { threshold: 1, city: null },
    });

    const { getPublicDiscoveryCategories } = await import('../data/discovery-categories');
    const result = await getPublicDiscoveryCategories();

    expect(result?.categories).toEqual([]);
  });

  it('repli du libellé sur la clé quand `label` est vide', async () => {
    mockFetch({
      data: [{ key: 'vtc', label: '', count: 5, visible: true }],
      meta: { threshold: 1, city: null },
    });

    const { getPublicDiscoveryCategories } = await import('../data/discovery-categories');
    const result = await getPublicDiscoveryCategories();

    expect(result?.categories[0].label).toBe('vtc');
  });

  it('API en erreur ⇒ null (indisponible), pas une liste vide', async () => {
    mockFetch({}, false, 503);

    const { getPublicDiscoveryCategories } = await import('../data/discovery-categories');
    expect(await getPublicDiscoveryCategories()).toBeNull();
  });

  it('exception réseau ⇒ null, jamais de throw', async () => {
    global.fetch = jest.fn(async () => {
      throw new Error('ECONNREFUSED');
    }) as unknown as typeof fetch;

    const { getPublicDiscoveryCategories } = await import('../data/discovery-categories');
    expect(await getPublicDiscoveryCategories()).toBeNull();
  });
});

describe('selectDisplayableCategories', () => {
  it('exige `visible` ET `count > 0`', async () => {
    const { selectDisplayableCategories } = await import('../data/discovery-categories');

    const result = selectDisplayableCategories([
      { key: 'ok', label: 'OK', count: 5, visible: true },
      // Seuil abaissé à 0 : `visible` vrai mais aucune Page ⇒ ne pas afficher.
      { key: 'empty', label: 'Vide', count: 0, visible: true },
      { key: 'hidden', label: 'Cachée', count: 40, visible: false },
    ]);

    expect(result.map((c) => c.key)).toEqual(['ok']);
  });
});
