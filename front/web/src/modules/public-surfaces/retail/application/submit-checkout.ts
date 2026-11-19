/**
 * Cas d'usage — verticale Retail : le passage de commande.
 *
 * Repris de `front/marketplace/src/app/commande/page.tsx` (#9627).
 *
 * La boucle d'envoi vit ici, pas dans le composant : c'est elle qui porte les
 * règles qui comptent (séquentialité, idempotence, succès partiel) et elle est
 * testable sans React.
 */

import type { CartGroup } from '../domain/cart';
import {
  buildOrderPayload,
  type CheckoutCustomer,
  type CheckoutOutcome,
  type CreatedOrder,
  type PaymentMethod,
} from '../domain/checkout';
import { createOrder } from '../infrastructure/market-gateway';
import { idempotencyKeyFor, releaseIdempotencyKey, saveOrders } from '../infrastructure/order-storage';
import { RETAIL_CHECKOUT_COPY } from '../../copy/retail-copy';

/**
 * Forme minimale d'un groupe de panier attendue par le checkout.
 *
 * On accepte `CartGroup` (domaine) comme `CartGroupView` (affichage) : les deux
 * portent le vendeur et ses lignes, et exiger une conversion à l'appelant
 * n'ajouterait qu'un mapping sans règle.
 */
export interface CheckoutGroup {
  readonly seller: { readonly slug: string; readonly name: string };
  readonly items: readonly { readonly productId: number; readonly quantity: number }[];
}

/**
 * Soumet une commande par boutique présente dans le panier.
 *
 * ## Pourquoi séquentiel
 *
 * Un envoi parallèle déclencherait le throttle public (`shop-public`) et
 * rendrait le succès partiel illisible : l'acheteur verrait trois erreurs
 * simultanées sans savoir laquelle a abouti. Séquentiel = lisible et doux.
 *
 * ## Succès partiel
 *
 * Une boutique servie est retirée du panier et sa clé d'idempotence libérée.
 * Une boutique en échec **conserve** sa clé : la re-soumission rejouera la même
 * commande côté serveur, sans doublon.
 */
export async function submitCheckout(params: {
  groups: readonly CheckoutGroup[];
  customer: CheckoutCustomer;
  paymentMethod: PaymentMethod;
  /** Retire une boutique du panier après succès. */
  onSellerServed: (sellerSlug: string) => void;
}): Promise<CheckoutOutcome> {
  const created: CreatedOrder[] = [];
  const failed: { sellerName: string; message: string }[] = [];

  for (const group of params.groups) {
    const key = idempotencyKeyFor(group.seller.slug);

    try {
      const order = await createOrder(
        buildOrderPayload(
          group as unknown as CartGroup,
          params.customer,
          params.paymentMethod,
          key,
        ),
      );

      created.push({
        reference: order.reference,
        trackingToken: order.trackingToken,
        totalMinor: order.totalMinor,
        currency: order.currency,
        sellerName: group.seller.name,
        sellerSlug: group.seller.slug,
        createdAt: new Date().toISOString(),
        paymentMethod: params.paymentMethod,
        checkoutUrl: order.checkoutUrl,
      });

      releaseIdempotencyKey(group.seller.slug);
      params.onSellerServed(group.seller.slug);
    } catch (error) {
      failed.push({
        sellerName: group.seller.name,
        message: error instanceof Error ? error.message : RETAIL_CHECKOUT_COPY.errorGeneric,
      });
    }
  }

  if (created.length > 0) {
    saveOrders(created);
  }

  return { created, failed };
}
