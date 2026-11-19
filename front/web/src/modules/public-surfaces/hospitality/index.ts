/**
 * Vitrine publique — verticale Hôtellerie (Stay).
 *
 * Point d'entrée unique du bounded context.
 */

export { loadPropertyView, type PropertyView } from './application/load-property-view';
export {
  loadPropertyDirectoryView,
  type PropertyDirectoryCard,
  type PropertyDirectoryResult,
  type PropertyDirectoryState,
} from './application/load-property-directory-view';
export {
  propertyDirectoryMeta,
  propertyKindLabel,
  type PropertyDirectoryEntry,
} from './domain/property-directory';
export {
  formatMoney,
  fromPrice,
  starLabel,
  type Property,
  type RoomType,
} from './domain/property';
export { HOSPITALITY_SURFACE } from './identity/hospitality-identity';
export { PropertySurface } from './components/PropertySurface';
export { PropertyDirectorySurface } from './components/PropertyDirectorySurface';
export { default as StayBookingPanel } from './components/StayBookingPanel';
export { formatStayMoney, stayPropertyTypeLabel } from './components/stay-format';
