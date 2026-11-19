import { RestaurantOrderTrackingSurface } from '@/modules/public-surfaces';

/**
 * RESTO-903 (#7748) — suivi de commande publique (`/order`).
 *
 * #9634 — adaptateur Next mince : la saisie de référence + jeton boutique et
 * l'appel `GET /public/restaurant/shop/orders/{ref}` vivent dans
 * `public-surfaces/restaurants/components/OrderTrackingSurface` (ADR-0034).
 *
 * `?ref=` déclenche un suivi immédiat ; le deep-link `?token=` du gérant reste
 * valide.
 */
export default function OrderPage() {
  return <RestaurantOrderTrackingSurface />;
}
