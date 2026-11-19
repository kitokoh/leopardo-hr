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

// #9627 — contenu repris de `front/marketplace` avant sa suppression.
export {
  displayProductRating,
  formatPrice,
  isPurchasable,
  type Product,
  type ProductSeller,
} from './domain/product';
export {
  MAX_QUANTITY,
  addItem,
  cartItemFromProduct,
  clampQuantity,
  countItems,
  groupBySeller,
  removeItem,
  removeSeller,
  sanitizeCartItem,
  setQuantity,
  subtotalOf,
  type CartGroup,
  type CartItem,
} from './domain/cart';
export {
  FULFILLMENT_SEQUENCE,
  FULFILLMENT_STATUSES,
  isFulfillmentStatus,
  isTerminal,
  normalizeTimeline,
  reachedSteps,
  sequenceIndex,
  type FulfillmentStatus,
  type OrderTracking,
  type TimelineEntry,
  type TrackedItem,
} from './domain/order';
export {
  availabilityLabel,
  loadCatalog,
  loadProductView,
  type CatalogResult,
  type ProductCard,
} from './application/load-catalog';
export {
  addProductToCart,
  buildCartView,
  cartCountLabel,
  changeQuantity,
  clearAll,
  clearSeller,
  loadCartView,
  removeProduct,
  type CartGroupView,
  type CartView,
} from './application/load-cart';
export {
  buildTrackingView,
  loadTrackingView,
  type OrderTrackingView,
  type TimelineStepView,
} from './application/load-order-tracking';
export {
  loadOrderTracking,
  loadProduct,
  searchProducts,
  toProduct,
  type ProductPage,
} from './infrastructure/market-gateway';
export {
  CART_EVENT,
  CART_STORAGE_KEY,
  readCart,
  subscribeToCart,
  writeCart,
} from './infrastructure/cart-storage';
export { CatalogSurface } from './components/CatalogSurface';
export { ProductSurface } from './components/ProductSurface';
export { CartSurface } from './components/CartSurface';
export { OrderTrackingSurface } from './components/OrderTrackingSurface';
