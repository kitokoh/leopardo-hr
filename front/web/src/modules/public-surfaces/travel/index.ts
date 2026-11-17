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
