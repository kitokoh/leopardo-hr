/**
 * Vitrine publique — verticale Mobilité (VTC / taxi) (#9622).
 *
 * Point d'entrée unique du bounded context.
 *
 * ## Pourquoi cette verticale existait sans vitrine
 *
 * `VtcDiscoverableProvider` publie `vtc_operator` depuis P1-02 (#8773) et le
 * contrat public accepte des courses invitées. Mais la verticale vivait
 * uniquement dans l'annuaire générique `/operateurs` (`buildDirectoryPage`),
 * sans identité ni composition propres : le grand public arrivait sur une liste
 * sommaire, et la verticale n'avait ni domaine canonique ni déploiement dédié.
 */

export {
  loadMobilitySurface,
  type MobilityCard,
  type MobilitySurfaceResult,
  type MobilitySurfaceState,
} from './application/load-mobility-surface';
export {
  MOBILITY_ACTIONS,
  currencyLabel,
  isContactable,
  operatorMeta,
  type MobilityAction,
  type MobilityOperator,
} from './domain/operator';
export { MOBILITY_SURFACE } from './identity/mobility-identity';
export { MobilitySurface } from './components/MobilitySurface';

// #9628 — courses invitées (repris de front/travel-web).
export {
  RIDE_SEQUENCE,
  RIDE_STATUSES,
  isPriced,
  isRideStatus,
  isRideTerminal,
  isValidGeoPoint,
  reachedRideSteps,
  validateRideRequest,
  type CreatedGuestRide,
  type GeoPoint,
  type GuestRide,
  type RideEstimate,
  type RideStatus,
} from './domain/ride';
export {
  VtcApiError,
  createRide,
  estimateRide,
  rideStatusLabel,
  trackRide,
} from './infrastructure/ride-gateway';
export { RideRequestSurface } from './components/RideRequestSurface';
export { RideTrackingSurface } from './components/RideTrackingSurface';
export { loadOperators, type MobilityLoadResult } from './infrastructure/operator-gateway';
