/**
 * Vitrine publique — verticale Hôtellerie (Stay).
 *
 * Point d'entrée unique du bounded context.
 */

export { loadPropertyView, type PropertyView } from './application/load-property-view';
export {
  formatMoney,
  fromPrice,
  starLabel,
  type Property,
  type RoomType,
} from './domain/property';
export { HOSPITALITY_SURFACE } from './identity/hospitality-identity';
export { PropertySurface } from './components/PropertySurface';
