import { buildDiscoveryQuery, DISCOVERY_MAX_PER_PAGE } from '@/lib/public-discovery-api';

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
