/**
 * Vitrine publique — verticale Restaurant.
 *
 * Point d'entrée unique du bounded context : la page n'importe que ceci, jamais
 * les couches internes. C'est ce qui permet de refactorer le domaine sans
 * toucher aux routes.
 */

export {
  loadRestaurantSurface,
  type RestaurantCard,
  type RestaurantSurfaceResult,
} from './application/load-restaurant-surface';
export {
  displayRating,
  formatDistance,
  isEmptyPage,
  sortRestaurants,
  type Restaurant,
  type RestaurantCriteria,
  type RestaurantPage,
} from './domain/restaurant';
export { RESTAURANT_SURFACE } from './identity/restaurant-identity';
export { RestaurantSurface } from './components/RestaurantSurface';
export { default as RestaurantExplorer } from './components/RestaurantExplorer';
export { default as RestaurantOrderPanel } from './components/RestaurantOrderPanel';
export { default as ShopOrderSurface } from './components/ShopOrderSurface';
export { default as RestaurantOrderTrackingSurface } from './components/RestaurantOrderTrackingSurface';
export {
  dayOfWeekName,
  establishmentTypeLabel,
  formatRestaurantMoney,
  formatRating,
} from './components/restaurant-format';
