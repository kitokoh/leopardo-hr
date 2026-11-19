/**
 * Cas d'usage — verticale Billets : la fiche trajet.
 *
 * Charge le trajet, résout la géométrie du plan de sièges et prépare
 * l'affichage. Ne lève jamais.
 */

import { SEAT_LABELS } from '../../copy/seat-labels';
import { getSurfaceCopy } from '../../copy/surfaces-copy';
import { formatTripPrice, isBookable, seatsLabel, tripRoute } from '../domain/trip';
import {
  buildSeatMapLayout,
  resolveSeatLayout,
  selectableSeatNumbers,
  type SeatMapLayout,
} from '../domain/seat-layout';
import { loadTripDetail, type TripDetail } from '../infrastructure/trip-detail-gateway';

/** Fiche trajet prête à l'affichage. */
export interface TripDetailView {
  readonly trip: TripDetail;
  readonly route: string;
  readonly priceLabel: string | null;
  readonly seats: string | null;
  readonly bookable: boolean;
  readonly seatMap: SeatMapLayout;
  readonly selectableSeats: readonly number[];
  /** `false` quand la géométrie du plan est déduite, pas lue sur le véhicule. */
  readonly layoutConfigured: boolean;
}

/** Compose l'affichage d'une fiche trajet. */
export function buildTripDetailView(trip: TripDetail, locale?: string): TripDetailView {
  const shared = getSurfaceCopy(locale).shared;
  const layout = resolveSeatLayout(trip.totalSeats, trip.seatLayout);

  return {
    trip,
    route: tripRoute(trip),
    priceLabel: formatTripPrice(trip.priceMinor, trip.currency),
    seats: seatsLabel(trip.seatsAvailable, shared),
    bookable: isBookable(trip),
    seatMap: buildSeatMapLayout(trip.seats, SEAT_LABELS, {
      seatsPerRow: layout.seatsPerRow,
      aisleAfter: layout.aisleAfter,
      absentSeats: layout.absentSeats,
    }),
    selectableSeats: selectableSeatNumbers(trip.seats),
    layoutConfigured: layout.isConfigured,
  };
}

/** Charge une fiche trajet ; `null` si absente ou API indisponible. */
export async function loadTripDetailView(
  id: string | number,
  locale?: string,
): Promise<TripDetailView | null> {
  const trip = await loadTripDetail(id);

  return trip === null ? null : buildTripDetailView(trip, locale);
}
