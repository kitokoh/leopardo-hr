/**
 * Domaine public — verticale Billets : la réservation.
 *
 * Repris de `front/travel-web/src/components/booking-flow.tsx` (#9628).
 *
 * ## Ce qui est une règle, et ce qui ne l'est pas
 *
 * - **Une clé d'idempotence par session de checkout** : un rejeu réseau ne
 *   crée jamais deux réservations. C'est la règle la plus importante du
 *   parcours, elle vit ici.
 * - **Le tarif par catégorie d'âge** : un adulte paie le tarif adulte, un
 *   enfant le tarif enfant, un **nourrisson ne paie pas de siège** (tarif
 *   tranché par l'agence). Inventer un prix pour un nourrisson serait faux.
 * - **Un contact obligatoire** : e-mail ou téléphone, au moins un. Sans
 *   contact, l'agence ne peut pas prévenir d'un changement d'horaire.
 * - **Un nom par passager** : un billet sans nom n'est pas un billet.
 */

/** Catégorie d'âge d'un passager. */
export type AgeCategory = 'adult' | 'child' | 'infant';

/** Tarif d'une classe de voyage. */
export interface TripPrice {
  readonly classId: number;
  readonly adultPriceMinor: number;
  readonly childPriceMinor: number;
  readonly currency: string;
}

/** Un passager en cours de saisie. */
export interface PassengerDraft {
  readonly fullName: string;
  readonly ageCategory: AgeCategory;
  readonly classId: number;
  readonly seatNumber: number | null;
}

/** Nombre maximal de passagers par réservation. */
export const MAX_PASSENGERS = 20;

/**
 * Prix d'un passager pour une classe donnée.
 *
 * Un nourrisson vaut **0** : il n'occupe pas de siège facturé. Ce n'est pas
 * une absence de donnée mais une règle tarifaire — d'où le `0` explicite
 * plutôt qu'un `null` que l'UI devrait interpréter.
 */
export function priceFor(price: TripPrice | undefined, age: AgeCategory): number {
  if (price === undefined) {
    return 0;
  }

  if (age === 'adult') {
    return price.adultPriceMinor;
  }

  if (age === 'child') {
    return price.childPriceMinor;
  }

  return 0;
}

/** Total d'une réservation, classe par classe. */
export function bookingTotal(
  passengers: readonly PassengerDraft[],
  prices: readonly TripPrice[],
): number {
  const byClass = new Map(prices.map((price) => [price.classId, price]));

  return passengers.reduce(
    (sum, passenger) => sum + priceFor(byClass.get(passenger.classId), passenger.ageCategory),
    0,
  );
}

/**
 * Valide une réservation avant envoi.
 *
 * Rend le premier problème rencontré, ou `null` si tout est bon. L'ordre des
 * contrôles suit l'ordre du formulaire : l'utilisateur est ramené au premier
 * champ fautif, pas au dernier.
 */
export function validateBooking(params: {
  passengers: readonly PassengerDraft[];
  contactEmail: string;
  contactPhone: string;
}): 'missing-name' | 'missing-contact' | null {
  if (params.passengers.some((passenger) => passenger.fullName.trim() === '')) {
    return 'missing-name';
  }

  if (params.contactEmail.trim() === '' && params.contactPhone.trim() === '') {
    return 'missing-contact';
  }

  return null;
}

/** Charge utile envoyée à `POST /public/travel/marketplace/bookings`. */
export interface BookingPayload {
  readonly trip_id: number;
  readonly idempotency_key: string;
  readonly contact_email?: string;
  readonly contact_phone?: string;
  readonly notify_consent: boolean;
  readonly passengers: readonly {
    readonly full_name: string;
    readonly age_category: AgeCategory;
    readonly class_id: number;
    readonly seat_number: number | null;
  }[];
}

/**
 * Construit la charge utile.
 *
 * Les coordonnées vides sont **omises** : envoyer `""` ferait échouer la
 * validation `email` de Laravel alors que le champ est facultatif.
 */
export function buildBookingPayload(params: {
  tripId: number;
  idempotencyKey: string;
  passengers: readonly PassengerDraft[];
  contactEmail: string;
  contactPhone: string;
  notifyConsent: boolean;
}): BookingPayload {
  const email = params.contactEmail.trim();
  const phone = params.contactPhone.trim();

  return {
    trip_id: params.tripId,
    idempotency_key: params.idempotencyKey,
    ...(email !== '' ? { contact_email: email } : {}),
    ...(phone !== '' ? { contact_phone: phone } : {}),
    notify_consent: params.notifyConsent,
    passengers: params.passengers.map((passenger) => ({
      full_name: passenger.fullName.trim(),
      age_category: passenger.ageCategory,
      class_id: passenger.classId,
      seat_number: passenger.seatNumber,
    })),
  };
}

/** Réservation créée. */
export interface CreatedBooking {
  readonly reference: string;
  readonly status: string;
  readonly passengerCount: number;
  readonly totalMinor: number;
  readonly currency: string;
  readonly paymentStatus: string;
  readonly expiresAt: string | null;
  readonly agencyName: string | null;
}

/**
 * Génère une clé d'idempotence.
 *
 * `crypto.randomUUID` n'existe pas partout (vieux navigateurs, contexte non
 * sécurisé) : le repli garde le parcours fonctionnel, au prix d'une
 * idempotence plus faible — mieux qu'un checkout impossible.
 */
export function newIdempotencyKey(): string {
  if (typeof crypto !== 'undefined' && 'randomUUID' in crypto) {
    return crypto.randomUUID();
  }

  return `web-${Date.now()}-${Math.random().toString(36).slice(2)}`;
}
