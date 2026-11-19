'use client';

/**
 * Passage de commande — verticale Retail (#9627).
 *
 * Repris de `front/marketplace/src/app/commande/page.tsx`. La logique d'envoi
 * (séquentialité, idempotence, succès partiel) est dans
 * `application/submit-checkout` : ce composant ne fait que collecter et rendre.
 */

import { useRouter } from 'next/navigation';
import { useState, type FormEvent } from 'react';

import { RETAIL_CHECKOUT_COPY as C, RETAIL_CONFIRMATION_COPY } from '../../copy/retail-copy';
import { SurfaceBadge, SurfaceCard, SurfaceNotice } from '../../kernel/components/SurfacePrimitives';
import type { SurfaceIdentity } from '../../kernel/domain/surface-identity';
import { buildCartView, type CartView } from '../application/load-cart';
import { submitCheckout } from '../application/submit-checkout';
import {
  EMPTY_CUSTOMER,
  immediateCheckoutUrl,
  missingFields,
  type CheckoutCustomer,
  type PaymentMethod,
} from '../domain/checkout';
import { removeSeller } from '../domain/cart';
import { formatPrice } from '../domain/product';
import { readCart, writeCart } from '../infrastructure/cart-storage';

const INPUT_STYLE = {
  height: '2.75rem',
  width: '100%',
  borderRadius: '0.75rem',
  border: '1px solid #D6D3D1',
  padding: '0 1rem',
  fontSize: '0.9rem',
} as const;

const LABEL_STYLE = {
  display: 'block',
  marginBottom: '0.25rem',
  fontSize: '0.75rem',
  fontWeight: 600,
  color: '#57534E',
} as const;

export function CheckoutSurface({
  identity,
  initialCart,
}: {
  identity: SurfaceIdentity;
  initialCart: CartView;
}) {
  const router = useRouter();
  const [cart, setCart] = useState<CartView>(initialCart);
  const [customer, setCustomer] = useState<CheckoutCustomer>(EMPTY_CUSTOMER);
  const [paymentMethod, setPaymentMethod] = useState<PaymentMethod>('cash');
  const [submitting, setSubmitting] = useState(false);
  const [failures, setFailures] = useState<{ sellerName: string; message: string }[]>([]);
  const [createdCount, setCreatedCount] = useState(0);

  const set = (field: keyof CheckoutCustomer) => (value: string) =>
    setCustomer((previous) => ({ ...previous, [field]: value }));

  const submit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();

    if (submitting || cart.groups.length === 0) {
      return;
    }

    if (missingFields(customer).length > 0) {
      setFailures([{ sellerName: 'Formulaire', message: C.errorRequired }]);

      return;
    }

    setSubmitting(true);
    setFailures([]);

    const outcome = await submitCheckout({
      groups: cart.groups.map((group) => ({
        seller: { slug: group.sellerSlug, name: group.sellerName },
        items: group.items.map((item) => ({
          productId: item.productId,
          quantity: item.quantity,
        })),
      })),
      customer,
      paymentMethod,
      onSellerServed: (sellerSlug) => {
        // Le panier local est la source de vérité : on le réécrit pour que la
        // boutique servie disparaisse aussi après un rechargement.
        const next = removeSeller(readCart(), sellerSlug);
        writeCart(next);
        setCart(buildCartView(next));
      },
    });

    setCreatedCount(outcome.created.length);

    if (outcome.failed.length === 0) {
      const url = immediateCheckoutUrl(outcome, paymentMethod);

      if (url !== null) {
        window.location.assign(url);

        return;
      }

      router.push('/confirmation');

      return;
    }

    setFailures([...outcome.failed]);
    setSubmitting(false);
  };

  if (cart.groups.length === 0) {
    return (
      <SurfaceNotice identity={identity}>
        {createdCount > 0
          ? C.successRecorded
          : C.emptyCart}
      </SurfaceNotice>
    );
  }

  return (
    <form onSubmit={submit} style={{ display: 'grid', gap: '1.5rem' }}>
      <SurfaceCard identity={identity}>
        <h2 style={{ margin: 0, fontSize: '0.95rem', fontWeight: 800 }}>Vos coordonnées</h2>

        <div style={{ display: 'grid', gap: '0.75rem', gridTemplateColumns: '1fr 1fr' }}>
          <div>
            <label htmlFor="checkout-name" style={LABEL_STYLE}>
              Nom complet *
            </label>
            <input
              id="checkout-name"
              required
              autoComplete="name"
              value={customer.name}
              onChange={(event) => set('name')(event.target.value)}
              style={INPUT_STYLE}
            />
          </div>
          <div>
            <label htmlFor="checkout-phone" style={LABEL_STYLE}>
              Téléphone *
            </label>
            <input
              id="checkout-phone"
              required
              type="tel"
              autoComplete="tel"
              value={customer.phone}
              onChange={(event) => set('phone')(event.target.value)}
              style={INPUT_STYLE}
            />
          </div>
        </div>

        <div>
          <label htmlFor="checkout-email" style={LABEL_STYLE}>
            E-mail (facultatif)
          </label>
          <input
            id="checkout-email"
            type="email"
            autoComplete="email"
            value={customer.email}
            onChange={(event) => set('email')(event.target.value)}
            style={INPUT_STYLE}
          />
        </div>
      </SurfaceCard>

      <SurfaceCard identity={identity}>
        <h2 style={{ margin: 0, fontSize: '0.95rem', fontWeight: 800 }}>Livraison</h2>

        <div>
          <label htmlFor="checkout-address" style={LABEL_STYLE}>
            Adresse *
          </label>
          <input
            id="checkout-address"
            required
            autoComplete="street-address"
            value={customer.address}
            onChange={(event) => set('address')(event.target.value)}
            style={INPUT_STYLE}
          />
        </div>

        <div>
          <label htmlFor="checkout-city" style={LABEL_STYLE}>
            Ville *
          </label>
          <input
            id="checkout-city"
            required
            autoComplete="address-level2"
            value={customer.city}
            onChange={(event) => set('city')(event.target.value)}
            style={INPUT_STYLE}
          />
        </div>

        <div>
          <label htmlFor="checkout-notes" style={LABEL_STYLE}>
            Instructions pour le livreur (facultatif)
          </label>
          <textarea
            id="checkout-notes"
            rows={3}
            value={customer.notes}
            onChange={(event) => set('notes')(event.target.value)}
            style={{ ...INPUT_STYLE, height: 'auto', padding: '0.75rem 1rem' }}
          />
        </div>
      </SurfaceCard>

      <SurfaceCard identity={identity}>
        <h2 style={{ margin: 0, fontSize: '0.95rem', fontWeight: 800 }}>Mode de paiement</h2>

        {(
          [
            ['cash', C.paymentOnDelivery, C.paymentOnDeliveryHint],
            ['online', C.paymentOnline, C.paymentOnlineHint],
          ] as [PaymentMethod, string, string][]
        ).map(([value, label, hint]) => (
          <label
            key={value}
            style={{
              display: 'flex',
              gap: '0.6rem',
              alignItems: 'flex-start',
              padding: '0.75rem',
              borderRadius: '0.75rem',
              border: `1px solid ${paymentMethod === value ? identity.palette.accent : '#E7E5E4'}`,
              background: paymentMethod === value ? `${identity.palette.accent}11` : '#FFFFFF',
              cursor: 'pointer',
            }}
          >
            <input
              type="radio"
              name="payment-method"
              value={value}
              checked={paymentMethod === value}
              onChange={() => setPaymentMethod(value)}
              style={{ marginTop: '0.2rem' }}
            />
            <span>
              <span style={{ display: 'block', fontSize: '0.9rem', fontWeight: 700 }}>{label}</span>
              <span style={{ display: 'block', fontSize: '0.78rem', color: '#78716C' }}>{hint}</span>
            </span>
          </label>
        ))}
      </SurfaceCard>

      <SurfaceCard identity={identity}>
        <h2 style={{ margin: 0, fontSize: '0.95rem', fontWeight: 800 }}>
          Récapitulatif — {cart.groups.length} commande{cart.groups.length > 1 ? 's' : ''}
        </h2>

        {cart.groups.map((group) => (
          <div key={group.sellerSlug} style={{ borderTop: '1px solid #F5F5F4', paddingTop: '0.6rem' }}>
            <p style={{ margin: 0, fontSize: '0.85rem', fontWeight: 700 }}>{group.sellerName}</p>
            <ul style={{ margin: '0.3rem 0 0', padding: 0, listStyle: 'none' }}>
              {group.items.map((item) => (
                <li
                  key={item.productId}
                  style={{ display: 'flex', justifyContent: 'space-between', fontSize: '0.82rem' }}
                >
                  <span>
                    {item.quantity} × {item.name}
                  </span>
                  <span style={{ fontWeight: 600 }}>
                    {formatPrice(item.priceMinor * item.quantity, item.currency)}
                  </span>
                </li>
              ))}
            </ul>
            <p style={{ margin: '0.3rem 0 0', fontSize: '0.82rem', color: '#78716C' }}>
              Sous-total : <strong>{group.subtotalLabel ?? '—'}</strong>
            </p>
          </div>
        ))}
      </SurfaceCard>

      {failures.length > 0 ? (
        <div role="alert" style={{ display: 'grid', gap: '0.4rem' }}>
          {failures.map((failure, index) => (
            <SurfaceNotice key={index} identity={identity} alert>
              <strong>{failure.sellerName} :</strong> {failure.message}
            </SurfaceNotice>
          ))}
        </div>
      ) : null}

      <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem' }}>
        <button
          type="submit"
          disabled={submitting}
          style={{
            padding: '0.8rem 1.4rem',
            borderRadius: '0.6rem',
            border: 'none',
            background: identity.palette.primary,
            color: identity.palette.onPrimary,
            fontWeight: 700,
            cursor: submitting ? 'not-allowed' : 'pointer',
            opacity: submitting ? 0.6 : 1,
          }}
        >
          {submitting
            ? C.submitting
            : `Confirmer ${cart.groups.length > 1 ? `les ${cart.groups.length} commandes` : RETAIL_CONFIRMATION_COPY.orderLabel}`}
        </button>
        <SurfaceBadge identity={identity}>
          {paymentMethod === 'cash' ? C.paymentOnDelivery : C.paymentOnline}
        </SurfaceBadge>
      </div>
    </form>
  );
}
