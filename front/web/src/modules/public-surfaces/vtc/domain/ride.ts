/**
 * Domaine public — verticale Mobilité : la course invitée.
 *
 * Repris de `front/travel-web/src/components/vtc-booking-flow.tsx` et
 * `src/lib/types.ts` (#9628).
 *
 * ## Surface 100 % invitée
 *
 * Aucun compte : la course se demande, se paie et se suit par référence +
 * code. Le code de suivi est présenté **une seule fois** (à la création) :
 * c'est la seule preuve d'accès du passager, et il n'est jamais renvoyé au
 * rejeu idempotent.
 *
 * ## Ce que le domaine ne porte pas
 *
 * Aucune position de chauffeur, aucun identifiant interne. Le DTO public est
 * strict : la vitrine ne fabrique rien.
 */

/** Point WGS 84 — validation stricte côté API. */
export interface GeoPoint {
  readonly lat: number;
  readonly lng: number;
}

/** Cycle de vie d'une course (miroir de `VtcRideStatus`). */
export const RIDE_STATUSES = [
  'requested',
  'dispatching',
  'accepted',
  'arrived',
  'in_progress',
  'completed',
  'expired',
  'cancelled',
] as const;

export type RideStatus = (typeof RIDE_STATUSES)[number];

/** Vrai si la valeur est un statut de course connu. */
export function isRideStatus(value: unknown): value is RideStatus {
  return typeof value === 'string' && (RIDE_STATUSES as readonly string[]).includes(value);
}

/**
 * Ordre canonique du parcours.
 *
 * `expired` et `cancelled` en sont absents : ce sont des sorties, pas des
 * étapes. Les placer dans la séquence ferait afficher « annulée » comme une
 * progression.
 */
export const RIDE_SEQUENCE: readonly RideStatus[] = [
  'requested',
  'dispatching',
  'accepted',
  'arrived',
  'in_progress',
  'completed',
];

/** Vrai si la course est terminée (aboutie, expirée ou annulée). */
export function isRideTerminal(status: RideStatus): boolean {
  return status === 'completed' || status === 'expired' || status === 'cancelled';
}

/**
 * Étapes franchies pour un statut donné.
 *
 * Une course annulée ou expirée ne prétend **pas** avoir progressé : on rend
 * un ensemble vide plutôt que de marquer les étapes antérieures comme
 * atteintes, ce qui laisserait croire à une course partielle.
 */
export function reachedRideSteps(status: RideStatus): readonly RideStatus[] {
  if (status === 'cancelled' || status === 'expired') {
    return [];
  }

  const index = RIDE_SEQUENCE.indexOf(status);

  return index < 0 ? [] : RIDE_SEQUENCE.slice(0, index + 1);
}

/** Devis public d'une course. */
export interface RideEstimate {
  readonly distanceM: number | null;
  readonly roadDistanceM: number | null;
  readonly durationS: number | null;
  readonly priceMinor: number | null;
  readonly currency: string | null;
}

/** Course invitée telle que l'API la rend. */
export interface GuestRide {
  readonly reference: string;
  readonly status: RideStatus;
  readonly pickupAddress: string | null;
  readonly dropoffAddress: string | null;
  readonly estimatedDurationS: number | null;
  readonly estimatedPriceMinor: number | null;
  readonly currency: string | null;
  readonly requestedAt: string | null;
}

/** Création : le code de suivi n'est présent qu'à la première réponse. */
export interface CreatedGuestRide extends GuestRide {
  readonly trackingCode: string | null;
}

/**
 * Vrai si un devis est chiffrable.
 *
 * `price_minor` vaut `null` quand l'opérateur n'a pas de grille tarifaire :
 * afficher « 0 » serait un mensonge, et promettre un prix qu'aucun service ne
 * facturera serait pire. La vitrine dit alors « prix à confirmer ».
 */
export function isPriced(estimate: RideEstimate): boolean {
  return estimate.priceMinor !== null && estimate.priceMinor >= 0;
}

/**
 * Valide un point géographique.
 *
 * L'API rejette hors bornes ; valider ici évite un aller-retour réseau pour
 * une erreur de saisie évidente.
 */
export function isValidGeoPoint(point: GeoPoint): boolean {
  return (
    Number.isFinite(point.lat) &&
    Number.isFinite(point.lng) &&
    point.lat >= -90 &&
    point.lat <= 90 &&
    point.lng >= -180 &&
    point.lng <= 180
  );
}

/**
 * Valide la demande de course avant envoi.
 *
 * Rend le premier problème rencontré, ou `null`. Le nom et le téléphone du
 * passager sont obligatoires : sans eux, le chauffeur ne peut pas le joindre.
 */
export function validateRideRequest(params: {
  pickup: GeoPoint;
  dropoff: GeoPoint;
  passengerName: string;
  passengerPhone: string;
}): 'invalid-pickup' | 'invalid-dropoff' | 'missing-name' | 'missing-phone' | null {
  if (!isValidGeoPoint(params.pickup)) {
    return 'invalid-pickup';
  }

  if (!isValidGeoPoint(params.dropoff)) {
    return 'invalid-dropoff';
  }

  if (params.passengerName.trim() === '') {
    return 'missing-name';
  }

  if (params.passengerPhone.trim() === '') {
    return 'missing-phone';
  }

  return null;
}
