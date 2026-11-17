/**
 * Vitrine publique — verticale Pharmacie.
 *
 * Point d'entrée unique du bounded context.
 */

export {
  loadPharmacySurface,
  type PharmacyCard,
  type PharmacySurfaceResult,
  type PharmacySurfaceState,
} from './application/load-pharmacy-surface';
export {
  dutyLabel,
  hasServices,
  type Pharmacy,
} from './domain/pharmacy';
export { PHARMACY_SURFACE } from './identity/pharmacy-identity';
export { PharmacySurface } from './components/PharmacySurface';
