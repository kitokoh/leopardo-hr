/**
 * Vitrine publique — verticale Retail (Marché).
 *
 * Point d'entrée unique du bounded context.
 */

export { loadShopView, type ShopView } from './application/load-shop-view';
export {
  loadShopDirectoryView,
  type ShopDirectoryCard,
  type ShopDirectoryResult,
  type ShopDirectoryState,
} from './application/load-shop-directory-view';
export { shopDirectoryMeta, type ShopDirectoryEntry } from './domain/shop-directory';
export {
  displayShopRating,
  formatCatalogSize,
  hasCategories,
  type Shop,
  type ShopCategory,
} from './domain/shop';
export { RETAIL_SURFACE } from './identity/retail-identity';
export { ShopSurface } from './components/ShopSurface';
export { ShopDirectorySurface } from './components/ShopDirectorySurface';
