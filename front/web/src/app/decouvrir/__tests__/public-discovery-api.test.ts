import {
  buildDiscoveryQuery,
  DISCOVERY_MAX_PER_PAGE,
  searchPublicDiscovery,
} from '@/lib/public-discovery-api';

/**
 * SUPERAPP-P0 (#8611) — contrat de la passerelle publique `/decouvrir`.
 *
 * Vérifie que la query string envoyée à `GET /api/v1/public/discover` respecte
 * exactement le contrat public : bornes de pagination, rayon borné, et surtout
 * qu'un `type` inconnu n'est JAMAIS transmis (l'API le refuse en 422).
 */

describe('buildDiscoveryQuery', () => {
  it('returns an empty query when no filter is provided', () => {
    expect(buildDiscoveryQuery({})).toBe('');
  });

  it('encodes the free-text and city filters', () => {
    const qs = buildDiscoveryQuery({ q: 'plombier', city: 'Casablanca' });
    expect(qs).toContain('q=plombier');
    expect(qs).toContain('city=Casablanca');
  });

  it('never sends an unknown type (the API answers 422)', () => {
    const qs = buildDiscoveryQuery({ type: 'not-a-vertical' }, ['restaurant', 'showcase']);
    expect(qs).not.toContain('type=');
  });

  it('sends a known canonical type', () => {
    const qs = buildDiscoveryQuery({ type: 'restaurant' }, ['restaurant', 'showcase']);
    expect(qs).toContain('type=restaurant');
  });

  it('bounds the radius and only sends it with a position', () => {
    expect(buildDiscoveryQuery({ near: '33.57,-7.58' })).toContain('radius_km=10');
    expect(buildDiscoveryQuery({ near: '33.57,-7.58', radius_km: 999 })).toContain('radius_km=50');
    expect(buildDiscoveryQuery({ radius_km: 5 })).not.toContain('radius_km');
  });

  it('caps per_page at the public contract maximum', () => {
    expect(buildDiscoveryQuery({ per_page: 500 })).toContain(`per_page=${DISCOVERY_MAX_PER_PAGE}`);
  });

  it('omits page=1 (canonical first page)', () => {
    expect(buildDiscoveryQuery({ page: 1 })).not.toContain('page=');
    expect(buildDiscoveryQuery({ page: 3 })).toContain('page=3');
  });
});

/**
 * #8761 — régression : `normalizeMeta` reconstruisait l'objet `meta` champ par
 * champ et oubliait `action_urls`. Le premier rendu SSR (celui que voient les
 * crawlers et les visiteurs avant toute interaction) perdait donc les
 * destinations d'action, et les CTA « Réserver » / « Commander » affichaient
 * « indisponible » alors que le backend les déclarait.
 */
describe('searchPublicDiscovery — meta normalization', () => {
  const originalFetch = global.fetch;

  afterEach(() => {
    global.fetch = originalFetch;
    jest.restoreAllMocks();
  });

  it('preserves action_urls and actions from the public payload', async () => {
    global.fetch = jest.fn().mockResolvedValue({
      ok: true,
      json: async () => ({
        data: [{ type: 'restaurant', slug: 'chez-fatima', name: 'Chez Fatima' }],
        meta: {
          total: 1,
          per_page: 20,
          page: 1,
          last_page: 1,
          types: ['restaurant'],
          radius_km: null,
          actions: { restaurant: ['booking', 'contact'] },
          action_urls: { restaurant: { booking: '/restaurants/{slug}#reserver' } },
        },
      }),
    }) as unknown as typeof fetch;

    const result = await searchPublicDiscovery({});

    expect(result).not.toBeNull();
    expect(result?.meta.actions).toEqual({ restaurant: ['booking', 'contact'] });
    expect(result?.meta.action_urls).toEqual({
      restaurant: { booking: '/restaurants/{slug}#reserver' },
    });
  });

  it('leaves action_urls undefined when the payload omits it (fail-closed)', async () => {
    global.fetch = jest.fn().mockResolvedValue({
      ok: true,
      json: async () => ({ data: [], meta: { total: 0, types: [] } }),
    }) as unknown as typeof fetch;

    const result = await searchPublicDiscovery({});

    expect(result?.meta.action_urls).toBeUndefined();
  });
});
