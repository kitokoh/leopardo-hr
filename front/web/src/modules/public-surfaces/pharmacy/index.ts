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
// #9592 — lecture unitaire par slug, utilisée par la fiche `/pharmacies/{slug}`.
export { loadPharmacy, type PharmacyLoadResult } from './infrastructure/pharmacy-gateway';
