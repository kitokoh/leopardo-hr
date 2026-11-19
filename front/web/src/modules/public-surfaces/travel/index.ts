/**
 * Vitrine publique — verticale Billets (Travel).
 *
 * Point d'entrée unique du bounded context.
 */

export {
  loadTravelSurface,
  type TravelSurfaceResult,
  type TripCard,
} from './application/load-travel-surface';
export {
  formatTripPrice,
  isBookable,
  seatsLabel,
  tripRoute,
  type Trip,
} from './domain/trip';
export { TRAVEL_SURFACE } from './identity/travel-identity';
export { TravelSurface } from './components/TravelSurface';

// #9628 — contenu repris de `front/travel-web` avant sa suppression.
export {
  LEGEND_ORDER,
  buildSeatMapLayout,
  countSeatsByStatus,
  isSeatSelectable,
  resolveSeatLayout,
  resolveSeatsPerRow,
  seatAriaLabel,
  seatStatusLabel,
  seatStatusSymbol,
  selectableSeatNumbers,
  type LayoutOptions,
  type ResolvedSeatLayout,
  type SeatCell,
  type SeatInput,
  type SeatLayoutConfig,
  type SeatMapLayout,
  type SeatRowLayout,
  type SeatStatusValue,
} from './domain/seat-layout';
export {
  reconcileSeatSelection,
  submittableSeatNumbers,
  unavailableSeatNumbers,
  type SeatReconciliation,
  type SeatSelectionDraft,
} from './domain/seat-selection';
export {
  buildTripDetailView,
  loadTripDetailView,
  type TripDetailView,
} from './application/load-trip-detail';
export {
  loadTripDetail,
  toTripDetail,
  type TripDetail,
} from './infrastructure/trip-detail-gateway';
export { TripDetailSurface } from './components/TripDetailSurface';
