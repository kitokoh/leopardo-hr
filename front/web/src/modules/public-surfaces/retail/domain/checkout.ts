/**
 * Domaine public — verticale Retail : le passage de commande.
 *
 * Repris de `front/marketplace/src/app/commande/page.tsx` et
 * `src/lib/orders.ts` (#9627).
 *
 * ## La règle qui structure tout
 *
 * Côté API, **1 commande = 1 vendeur**. Un panier qui contient trois boutiques
 * produit donc trois commandes, et un succès partiel est un état normal : les
 * boutiques servies sortent du panier, les autres restent soumissables.
 *
 * ## Idempotence
 *
 * Chaque boutique reçoit une clé `crypto.randomUUID()` **persistée avant le
 * premier envoi**. Un double clic ou une coupure réseau rejoue la même clé et
 * le serveur répond de façon idempotente : jamais de commande dupliquée. La
 * clé n'est libérée qu'après un succès confirmé.
 */

import type { CartGroup } from './cart';

/** Moyen de paiement proposé au checkout. */
export type PaymentMethod = 'cash' | 'online';

/** Coordonnées de livraison saisies par l'acheteur. */
export interface CheckoutCustomer {
  readonly name: string;
  readonly phone: string;
  readonly email: string;
  readonly address: string;
  readonly city: string;
  readonly notes: string;
}

/** Formulaire vide — l'état initial, exporté pour que les tests le réutilisent. */
export const EMPTY_CUSTOMER: CheckoutCustomer = {
  name: '',
  phone: '',
  email: '',
  address: '',
  city: '',
  notes: '',
};

/**
 * Champs réellement obligatoires.
 *
 * L'e-mail est **facultatif** : le checkout invité ne doit pas exiger une
 * adresse pour livrer. Le rendre obligatoire ferait perdre des commandes.
 */
export const REQUIRED_FIELDS = ['name', 'phone', 'address', 'city'] as const;

export type RequiredField = (typeof REQUIRED_FIELDS)[number];

/**
 * Valide le formulaire et rend les champs manquants.
 *
 * Rend un tableau vide quand tout est bon — l'appelant teste `length === 0`
 * plutôt qu'un booléen, pour pouvoir surligner les champs fautifs.
 */
export function missingFields(customer: CheckoutCustomer): RequiredField[] {
  return REQUIRED_FIELDS.filter((field) => customer[field].trim() === '');
}

/** Charge utile envoyée à `POST /public/market/orders`, une par boutique. */
export interface OrderPayload {
  readonly seller: string;
  readonly items: readonly { readonly product_id: number; readonly quantity: number }[];
  readonly customer: { readonly name: string; readonly phone: string; readonly email?: string };
  readonly delivery: {
    readonly address: string;
    readonly city: string;
    readonly notes?: string;
  };
  readonly payment_method: PaymentMethod;
  readonly idempotency_key: string;
}

/**
 * Construit la charge utile d'une boutique.
 *
 * Les champs facultatifs vides sont **omis** plutôt qu'envoyés à `""` : l'API
 * distingue « pas d'e-mail » de « e-mail vide », et une chaîne vide déclenche
 * la validation `email` de Laravel.
 */
export function buildOrderPayload(
  group: CartGroup,
  customer: CheckoutCustomer,
  paymentMethod: PaymentMethod,
  idempotencyKey: string,
): OrderPayload {
  const email = customer.email.trim();
  const notes = customer.notes.trim();

  return {
    seller: group.seller.slug,
    items: group.items.map((item) => ({ product_id: item.productId, quantity: item.quantity })),
    customer: {
      name: customer.name.trim(),
      phone: customer.phone.trim(),
      ...(email !== '' ? { email } : {}),
    },
    delivery: {
      address: customer.address.trim(),
      city: customer.city.trim(),
      ...(notes !== '' ? { notes } : {}),
    },
    payment_method: paymentMethod,
    idempotency_key: idempotencyKey,
  };
}

/** Réponse de création d'une commande. */
export interface CreatedOrder {
  readonly reference: string;
  readonly trackingToken: string;
  readonly totalMinor: number;
  readonly currency: string;
  readonly sellerName: string;
  readonly sellerSlug: string;
  readonly createdAt: string;
  readonly paymentMethod: PaymentMethod;
  /** URL de paiement hébergée par le PSP, quand `payment_method = online`. */
  readonly checkoutUrl: string | null;
}

/**
 * Une commande enregistrée localement, retrouvable après rechargement.
 *
 * Le jeton de suivi y figure : c'est ce qui permet à `/suivi` de fonctionner
 * sans compte. Il ne quitte jamais le navigateur (jamais transmis au PSP).
 */
export type SavedOrder = CreatedOrder;

/**
 * Résultat d'une soumission multi-boutiques.
 *
 * `created` et `failed` coexistent : un succès partiel n'est pas une erreur,
 * c'est un état à présenter honnêtement.
 */
export interface CheckoutOutcome {
  readonly created: readonly CreatedOrder[];
  readonly failed: readonly { readonly sellerName: string; readonly message: string }[];
}

/** Vrai si toutes les boutiques ont été servies. */
export function isComplete(outcome: CheckoutOutcome): boolean {
  return outcome.failed.length === 0;
}

/**
 * URL de paiement à ouvrir immédiatement, s'il y en a une.
 *
 * Règle reprise de l'app d'origine : on ne redirige vers le PSP que si le
 * paiement est en ligne **et** qu'une seule commande est payable. Avec
 * plusieurs commandes, la page de confirmation affiche un bouton par commande —
 * rediriger vers la première ferait croire que les autres sont payées.
 */
export function immediateCheckoutUrl(
  outcome: CheckoutOutcome,
  paymentMethod: PaymentMethod,
): string | null {
  if (paymentMethod !== 'online') {
    return null;
  }

  const payable = outcome.created.filter((order) => order.checkoutUrl !== null);

  return payable.length === 1 ? payable[0].checkoutUrl : null;
}
