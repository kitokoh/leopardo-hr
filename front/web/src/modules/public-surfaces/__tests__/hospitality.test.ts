import { formatMoney, fromPrice, starLabel, type Property } from '../hospitality/domain/property';
import { toProperty } from '../hospitality/infrastructure/property-gateway';
import { HOSPITALITY_SURFACE } from '../hospitality/identity/hospitality-identity';
import { surfaceRegistry } from '../registry';

function property(overrides: Partial<Property> = {}): Property {
  return {
    slug: 'villa-bleue',
    name: 'Villa Bleue',
    kind: 'hotel',
    city: 'Nice',
    country: 'FR',
    starRating: 4,
    amenities: ['wifi'],
    roomTypes: [],
    ...overrides,
  };
}

describe('domaine hôtellerie — prix d’appel', () => {
  it('renvoie null sans type de chambre', () => {
    expect(fromPrice(property())).toBeNull();
  });

  it('choisit le vrai minimum, pas le premier de la liste', () => {
    const price = fromPrice(
      property({
        roomTypes: [
          { code: 'suite', name: 'Suite', description: null, basePriceMinor: 30000, currency: 'EUR' },
          { code: 'std', name: 'Standard', description: null, basePriceMinor: 9000, currency: 'EUR' },
        ],
      }),
    );

    expect(price).toEqual({ amountMinor: 9000, currency: 'EUR' });
  });
});

describe('domaine hôtellerie — montants', () => {
  it('formate en unité majeure', () => {
    expect(formatMoney(9000, 'EUR')).toBe('90 EUR');
    expect(formatMoney(9050, 'EUR')).toBe('90,50 EUR');
  });

  it('renvoie null sur un montant invalide', () => {
    expect(formatMoney(null, 'EUR')).toBeNull();
    expect(formatMoney(-1, 'EUR')).toBeNull();
  });
});

describe('domaine hôtellerie — étoiles', () => {
  it('rend les étoiles', () => {
    expect(starLabel(4)).toBe('★★★★');
  });

  it('renvoie null si non classé', () => {
    expect(starLabel(null)).toBeNull();
    expect(starLabel(0)).toBeNull();
  });
});

describe('adaptateur hôtellerie', () => {
  it('traduit la fiche publique vers le domaine', () => {
    const mapped = toProperty({
      slug: 'villa-bleue',
      name: 'Villa Bleue',
      type: 'hotel',
      address: null,
      city: 'Nice',
      country: 'FR',
      timezone: null,
      currency: 'EUR',
      phone: null,
      email: null,
      star_rating: 4,
      amenities: ['wifi'],
      latitude: null,
      longitude: null,
      room_types: [
        { id: 1, code: 'std', name: 'Standard', description: null, base_price_minor: 9000, currency: 'EUR' },
      ],
    });

    expect(mapped.roomTypes[0].basePriceMinor).toBe(9000);
    expect(mapped.amenities).toEqual(['wifi']);
  });
});

describe('identité hôtellerie', () => {
  it('est déclarée avec son propre domaine', () => {
    expect(surfaceRegistry.get('hospitality')).toBe(HOSPITALITY_SURFACE);
    expect(HOSPITALITY_SURFACE.canonicalDomain).toBe('sejours.leopardo.app');
    expect(HOSPITALITY_SURFACE.basePath).toBe('/stay');
  });
});
