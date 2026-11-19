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

// #9627 — checkout, compte, favoris, avis (repris de front/marketplace).
export {
  EMPTY_CUSTOMER,
  REQUIRED_FIELDS,
  buildOrderPayload,
  immediateCheckoutUrl,
  isComplete,
  missingFields,
  type CheckoutCustomer,
  type CheckoutOutcome,
  type CreatedOrder,
  type OrderPayload,
  type PaymentMethod,
  type SavedOrder,
} from './domain/checkout';
export {
  BUYER_EVENT,
  BUYER_STORAGE_KEY,
  canReview,
  isValidBuyer,
  isValidRating,
  normalizeBuyer,
  type AccountOrder,
  type BuyerProfile,
  type BuyerSession,
  type ProductReview,
  type ProductReviews,
} from './domain/buyer';
// #9652 — l'E/S navigateur vit en infrastructure, plus dans le domaine.
export {
  clearBuyerSession,
  readBuyerSession,
  takeLegacyBuyerToken,
  writeBuyerSession,
} from './infrastructure/buyer-session-storage';
export { submitCheckout, type CheckoutGroup } from './application/submit-checkout';
export {
  forgetSession,
  persistSession,
  restoreSession,
} from './application/load-buyer-session';
export {
  CHECKOUT_KEYS_STORAGE_KEY,
  MAX_SAVED_ORDERS,
  ORDERS_STORAGE_KEY,
  findSavedOrder,
  idempotencyKeyFor,
  readSavedOrders,
  releaseIdempotencyKey,
  saveOrders,
} from './infrastructure/order-storage';
export {
  AccountApiError,
  addFavorite,
  fetchBuyerOrders,
  fetchBuyerProfile,
  fetchFavorites,
  fetchProductReviews,
  loginBuyer,
  logoutBuyer,
  registerBuyer,
  removeFavorite,
  restoreBuyerSession,
  submitReview,
} from './infrastructure/account-gateway';
export { createOrder } from './infrastructure/market-gateway';
export { CheckoutSurface } from './components/CheckoutSurface';
export { BuyerAccountSurface } from './components/BuyerAccountSurface';
export { BuyerFavoritesSurface } from './components/BuyerFavoritesSurface';
export { BuyerOrdersSurface } from './components/BuyerOrdersSurface';
export { OrderConfirmationSurface } from './components/OrderConfirmationSurface';
export { ProductReviewsSurface } from './components/ProductReviewsSurface';
export { PaymentReturnSurface, PAYMENT_MAX_POLLS, PAYMENT_POLL_INTERVAL_MS } from './components/PaymentReturnSurface';
