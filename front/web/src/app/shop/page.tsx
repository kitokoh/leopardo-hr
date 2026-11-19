import { ShopOrderSurface } from '@/modules/public-surfaces';

/**
 * RESTO-805-front (#6404) — commande en ligne publique (`/shop`).
 *
 * #9634 — la page n'est plus qu'un **adaptateur Next** : le parcours
 * (menu → panier → commande idempotente → suivi → paiement) vit dans
 * `public-surfaces/restaurants/components/ShopOrderSurface`, conformément à
 * ADR-0034. Aucune logique métier ne subsiste ici.
 *
 * Page publique (hors dashboard, aucune session employé) : le tenant est
 * résolu par le jeton boutique (`X-Restaurant-Shop-Token`) transmis via
 * `?token=` (lien généré par le gérant, middleware `restaurant.public.shop`).
 */
export default function ShopPage() {
  return <ShopOrderSurface />;
}
