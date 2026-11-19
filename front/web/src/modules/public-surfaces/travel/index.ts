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
  type SeatLabels,
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

// #9628 — réservation et suivi (repris de front/travel-web).
export {
  MAX_PASSENGERS,
  bookingTotal,
  buildBookingPayload,
  newIdempotencyKey,
  priceFor,
  validateBooking,
  type AgeCategory,
  type BookingPayload,
  type CreatedBooking,
  type PassengerDraft,
  type TripPrice,
} from './domain/booking';
export {
  TravelApiError,
  cancelBooking,
  createBooking,
  trackBooking,
  type TrackedBooking,
} from './infrastructure/booking-gateway';
export { BookingSurface, SEAT_REFRESH_INTERVAL_MS } from './components/BookingSurface';
export { BookingTrackingSurface } from './components/BookingTrackingSurface';
export { TravelerAccountSurface } from './components/TravelerAccountSurface';
export { fetchMyBookings, type CustomerBooking } from './infrastructure/account-gateway';
