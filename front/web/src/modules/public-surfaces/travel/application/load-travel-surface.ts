/**
 * Cas d'usage — verticale Billets.
 *
 * Charge les trajets et prépare leur affichage (prix, places, réservabilité).
 */

import {
  formatTripPrice,
  isBookable,
  seatsLabel,
  type Trip,
} from '../domain/trip';
import { searchTrips } from '../infrastructure/trip-gateway';
import { getSurfaceCopy } from '../../copy/surfaces-copy';

/** Trajet prêt à l'affichage. */
export interface TripCard {
  readonly trip: Trip;
  readonly priceLabel: string | null;
  readonly seats: string | null;
  readonly bookable: boolean;
}

/** Résultat d'un chargement de vitrine billets. */
export interface TravelSurfaceResult {
  readonly cards: readonly TripCard[];
  /** `true` si l'API publique n'a pas répondu. */
  readonly failed: boolean;
}

/** Charge la vitrine billets ; ne lève jamais. */
export async function loadTravelSurface(params: {
  origin?: string;
  destination?: string;
  page?: number;
  locale?: string;
}): Promise<TravelSurfaceResult> {
  const trips = await searchTrips(params);

  if (trips === null) {
    return { cards: [], failed: true };
  }

  const shared = getSurfaceCopy(params.locale).shared;

  return {
    cards: trips.map((trip) => ({
      trip,
      priceLabel: formatTripPrice(trip.priceMinor, trip.currency),
      seats: seatsLabel(trip.seatsAvailable, shared),
      bookable: isBookable(trip),
    })),
    failed: false,
  };
}
