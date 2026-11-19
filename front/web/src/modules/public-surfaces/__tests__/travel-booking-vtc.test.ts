import {
  MAX_PASSENGERS,
  bookingTotal,
  buildBookingPayload,
  newIdempotencyKey,
  priceFor,
  validateBooking,
  type PassengerDraft,
  type TripPrice,
} from '../travel/domain/booking';
import {
  RIDE_SEQUENCE,
  isPriced,
  isRideStatus,
  isRideTerminal,
  isValidGeoPoint,
  reachedRideSteps,
  validateRideRequest,
} from '../vtc/domain/ride';

const PRICES: TripPrice[] = [
  { classId: 1, adultPriceMinor: 250000, childPriceMinor: 150000, currency: 'DZD' },
  { classId: 2, adultPriceMinor: 400000, childPriceMinor: 250000, currency: 'DZD' },
];

const passenger = (over: Partial<PassengerDraft> = {}): PassengerDraft => ({
  fullName: 'Yacine M.',
  ageCategory: 'adult',
  classId: 1,
  seatNumber: null,
  ...over,
});

describe('réservation — tarifs', () => {
  it('facture le tarif adulte', () => {
    expect(priceFor(PRICES[0], 'adult')).toBe(250000);
  });

  it('facture le tarif enfant', () => {
    expect(priceFor(PRICES[0], 'child')).toBe(150000);
  });

  it('ne facture pas de siège pour un nourrisson', () => {
    // Règle tarifaire, pas absence de donnée : 0 explicite.
    expect(priceFor(PRICES[0], 'infant')).toBe(0);
  });

  it('rend 0 pour une classe inconnue plutôt que NaN', () => {
    expect(priceFor(undefined, 'adult')).toBe(0);
  });

  it('additionne par classe de voyage', () => {
    const total = bookingTotal(
      [passenger(), passenger({ classId: 2 }), passenger({ ageCategory: 'infant' })],
      PRICES,
    );

    expect(total).toBe(250000 + 400000 + 0);
  });
});

describe('réservation — validation', () => {
  it('accepte une réservation complète', () => {
    expect(
      validateBooking({ passengers: [passenger()], contactEmail: 'a@b.dz', contactPhone: '' }),
    ).toBeNull();
  });

  it('exige un nom par passager', () => {
    expect(
      validateBooking({
        passengers: [passenger(), passenger({ fullName: '  ' })],
        contactEmail: 'a@b.dz',
        contactPhone: '',
      }),
    ).toBe('missing-name');
  });

  it('exige au moins un contact', () => {
    expect(
      validateBooking({ passengers: [passenger()], contactEmail: '', contactPhone: '' }),
    ).toBe('missing-contact');
  });

  it('accepte un téléphone seul', () => {
    expect(
      validateBooking({ passengers: [passenger()], contactEmail: '', contactPhone: '+213 555' }),
    ).toBeNull();
  });
});

describe('réservation — charge utile', () => {
  it('omet les coordonnées vides', () => {
    const payload = buildBookingPayload({
      tripId: 42,
      idempotencyKey: 'k',
      passengers: [passenger({ seatNumber: 12 })],
      contactEmail: '',
      contactPhone: '',
      notifyConsent: false,
    });

    expect('contact_email' in payload).toBe(false);
    expect('contact_phone' in payload).toBe(false);
  });

  it('transmet le siège choisi', () => {
    const payload = buildBookingPayload({
      tripId: 42,
      idempotencyKey: 'k',
      passengers: [passenger({ seatNumber: 12 })],
      contactEmail: 'a@b.dz',
      contactPhone: '',
      notifyConsent: true,
    });

    expect(payload.passengers[0].seat_number).toBe(12);
  });

  it('transmet un siège null quand l’attribution est automatique', () => {
    const payload = buildBookingPayload({
      tripId: 42,
      idempotencyKey: 'k',
      passengers: [passenger()],
      contactEmail: 'a@b.dz',
      contactPhone: '',
      notifyConsent: false,
    });

    expect(payload.passengers[0].seat_number).toBeNull();
  });

  it('génère une clé d’idempotence non vide et unique', () => {
    const first = newIdempotencyKey();
    const second = newIdempotencyKey();

    expect(first.length).toBeGreaterThan(0);
    expect(first).not.toBe(second);
  });

  it('plafonne le nombre de passagers', () => {
    expect(MAX_PASSENGERS).toBeGreaterThan(0);
  });
});

describe('course VTC — statuts', () => {
  it('reconnaît les statuts connus', () => {
    expect(isRideStatus('accepted')).toBe(true);
    expect(isRideStatus('teleported')).toBe(false);
  });

  it('marque les sorties comme terminales', () => {
    expect(isRideTerminal('completed')).toBe(true);
    expect(isRideTerminal('cancelled')).toBe(true);
    expect(isRideTerminal('expired')).toBe(true);
    expect(isRideTerminal('in_progress')).toBe(false);
  });

  it('ne prétend pas avoir progressé pour une course annulée', () => {
    // Marquer les étapes antérieures laisserait croire à une course partielle.
    expect(reachedRideSteps('cancelled')).toEqual([]);
    expect(reachedRideSteps('expired')).toEqual([]);
  });

  it('rend les étapes franchies dans l’ordre', () => {
    expect(reachedRideSteps('accepted')).toEqual(['requested', 'dispatching', 'accepted']);
  });

  it('n’inclut pas les sorties dans la séquence', () => {
    expect(RIDE_SEQUENCE).not.toContain('cancelled');
    expect(RIDE_SEQUENCE).not.toContain('expired');
  });
});

describe('course VTC — devis et validation', () => {
  it('considère un prix nul comme chiffrable', () => {
    expect(
      isPriced({ distanceM: 1, roadDistanceM: 1, durationS: 1, priceMinor: 0, currency: 'DZD' }),
    ).toBe(true);
  });

  it('ne considère pas un prix absent comme chiffrable', () => {
    expect(
      isPriced({ distanceM: 1, roadDistanceM: 1, durationS: 1, priceMinor: null, currency: null }),
    ).toBe(false);
  });

  it('borne les coordonnées géographiques', () => {
    expect(isValidGeoPoint({ lat: 36.75, lng: 3.05 })).toBe(true);
    expect(isValidGeoPoint({ lat: 91, lng: 3.05 })).toBe(false);
    expect(isValidGeoPoint({ lat: 36.75, lng: 181 })).toBe(false);
  });

  it('rejette un point de départ invalide', () => {
    expect(
      validateRideRequest({
        pickup: { lat: 200, lng: 0 },
        dropoff: { lat: 36, lng: 3 },
        passengerName: 'A',
        passengerPhone: '1',
      }),
    ).toBe('invalid-pickup');
  });

  it('exige un nom et un téléphone', () => {
    const base = {
      pickup: { lat: 36, lng: 3 },
      dropoff: { lat: 36.1, lng: 3.1 },
      passengerName: '',
      passengerPhone: '1',
    };

    expect(validateRideRequest(base)).toBe('missing-name');
    expect(validateRideRequest({ ...base, passengerName: 'A', passengerPhone: ' ' })).toBe(
      'missing-phone',
    );
  });
});
