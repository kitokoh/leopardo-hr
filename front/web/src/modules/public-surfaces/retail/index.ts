/**
 * Vitrine publique — verticale Retail (Marché).
 *
 * Point d'entrée unique du bounded context.
 */

export { loadShopView, type ShopView } from './application/load-shop-view';
export {
  displayShopRating,
  formatCatalogSize,
  hasCategories,
  type Shop,
  type ShopCategory,
} from './domain/shop';
export { RETAIL_SURFACE } from './identity/retail-identity';
export { ShopSurface } from './components/ShopSurface';
