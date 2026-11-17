import { formatTripPrice, isBookable, seatsLabel, tripRoute, type Trip } from '../travel/domain/trip';
import { toTrip } from '../travel/infrastructure/trip-gateway';
import { TRAVEL_SURFACE } from '../travel/identity/travel-identity';
import { surfaceRegistry } from '../registry';

function trip(overrides: Partial<Trip> = {}): Trip {
  return {
    reference: 'TR-1',
    origin: 'Lyon',
    destination: 'Paris',
    departureAt: '2026-12-01T08:00:00Z',
    arrivalAt: null,
    priceMinor: 2500,
    currency: 'EUR',
    seatsAvailable: 12,
    ...overrides,
  };
}

describe('domaine billets — réservabilité', () => {
  it('est réservable si des places restent', () => {
    expect(isBookable(trip())).toBe(true);
  });

  it('n’est pas réservable si complet', () => {
    expect(isBookable(trip({ seatsAvailable: 0 }))).toBe(false);
  });

  it('ne déclare pas complet quand la disponibilité est inconnue', () => {
    expect(isBookable(trip({ seatsAvailable: null }))).toBe(true);
  });
});

describe('domaine billets — places', () => {
  it('accorde le pluriel', () => {
    expect(seatsLabel(1)).toBe('1 place');
    expect(seatsLabel(12)).toBe('12 places');
  });

  it('signale le complet', () => {
    expect(seatsLabel(0)).toBe('Complet');
  });

  it('renvoie null si inconnu', () => {
    expect(seatsLabel(null)).toBeNull();
  });
});

describe('domaine billets — prix et itinéraire', () => {
  it('formate le prix', () => {
    expect(formatTripPrice(2500, 'EUR')).toBe('25 EUR');
  });

  it('renvoie null sur un prix invalide', () => {
    expect(formatTripPrice(null, 'EUR')).toBeNull();
  });

  it('compose l’itinéraire', () => {
    expect(tripRoute(trip())).toBe('Lyon → Paris');
  });
});

describe('adaptateur billets', () => {
  it('traduit un trajet brut vers le domaine', () => {
    const mapped = toTrip({
      reference: 'TR-1',
      origin: 'Lyon',
      destination: 'Paris',
      departure_at: '2026-12-01T08:00:00Z',
      price_minor: 2500,
      currency: 'EUR',
      seats_available: 12,
    });

    expect(mapped.reference).toBe('TR-1');
    expect(mapped.priceMinor).toBe(2500);
  });

  it('tolère les champs absents', () => {
    const mapped = toTrip({ id: 7 });

    expect(mapped.reference).toBe('7');
    expect(mapped.origin).toBe('Départ');
    expect(mapped.seatsAvailable).toBeNull();
  });
});

describe('identité billets', () => {
  it('est déclarée avec son propre domaine', () => {
    expect(surfaceRegistry.get('travel')).toBe(TRAVEL_SURFACE);
    expect(TRAVEL_SURFACE.canonicalDomain).toBe('billets.leopardo.app');
    expect(TRAVEL_SURFACE.basePath).toBe('/travel');
  });
});
