'use client';

/**
 * Confirmation de commande — verticale Retail (#9627).
 *
 * Repris de `front/marketplace/src/app/confirmation/page.tsx`. Affiche les
 * commandes enregistrées localement (référence + jeton de suivi) et, pour un
 * paiement en ligne, un bouton « Payer en ligne » par commande encore payable.
 *
 * Le jeton de suivi ne quitte jamais le navigateur : il n'est pas transmis au
 * prestataire de paiement, seulement relu ici.
 */

import Link from 'next/link';
import { useEffect, useState } from 'react';

import { SurfaceBadge, SurfaceCard, SurfaceNotice } from '../../kernel/components/SurfacePrimitives';
import type { SurfaceIdentity } from '../../kernel/domain/surface-identity';
import type { SavedOrder } from '../domain/checkout';
import { formatPrice } from '../domain/product';
import { readSavedOrders } from '../infrastructure/order-storage';

export function OrderConfirmationSurface({ identity }: { identity: SurfaceIdentity }) {
  const [orders, setOrders] = useState<SavedOrder[] | null>(null);

  useEffect(() => {
    setOrders(readSavedOrders());
  }, []);

  if (orders === null) {
    return <SurfaceNotice identity={identity}>Chargement de vos commandes…</SurfaceNotice>;
  }

  if (orders.length === 0) {
    return (
      <SurfaceNotice identity={identity}>
        Aucune commande récente sur cet appareil. <Link href="/produits">Voir les produits</Link>
      </SurfaceNotice>
    );
  }

  return (
    <div style={{ display: 'grid', gap: '0.9rem' }}>
      <p style={{ margin: 0, fontSize: '0.9rem', color: '#57534E' }}>
        {orders.length} commande{orders.length > 1 ? 's' : ''} enregistrée
        {orders.length > 1 ? 's' : ''}. Conservez ces références : elles suffisent à suivre votre
        livraison, sans compte.
      </p>

      <ul style={{ listStyle: 'none', margin: 0, padding: 0, display: 'grid', gap: '0.75rem' }}>
        {orders.map((order) => (
          <SurfaceCard key={order.reference} identity={identity} as="li">
            <div style={{ display: 'flex', justifyContent: 'space-between', gap: '0.75rem' }}>
              <div>
                <p style={{ margin: 0, fontSize: '0.9rem', fontWeight: 700 }}>{order.sellerName}</p>
                <p style={{ margin: '0.15rem 0 0', fontSize: '0.78rem', color: '#78716C' }}>
                  Référence {order.reference}
                </p>
              </div>
              <SurfaceBadge identity={identity} tone="accent">
                {order.paymentMethod === 'online' ? 'Paiement en ligne' : 'À la livraison'}
              </SurfaceBadge>
            </div>

            <p style={{ margin: '0.4rem 0 0', fontSize: '0.95rem', fontWeight: 800 }}>
              {formatPrice(order.totalMinor, order.currency)}
            </p>

            <div style={{ display: 'flex', gap: '0.9rem', marginTop: '0.4rem', fontSize: '0.82rem' }}>
              <Link
                href={`/suivi?reference=${encodeURIComponent(order.reference)}&token=${encodeURIComponent(order.trackingToken)}`}
                style={{ color: identity.palette.primary, fontWeight: 600 }}
              >
                Suivre ma commande
              </Link>

              {order.checkoutUrl !== null ? (
                <a
                  href={order.checkoutUrl}
                  style={{ color: identity.palette.primary, fontWeight: 600 }}
                >
                  Payer en ligne
                </a>
              ) : null}
            </div>
          </SurfaceCard>
        ))}
      </ul>
    </div>
  );
}
