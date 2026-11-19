'use client';

/**
 * Mes commandes — verticale Retail (#9627).
 *
 * Repris de `front/marketplace/src/app/compte/commandes/page.tsx`. L'historique
 * vient du compte (cookie de session) ; le suivi détaillé reste accessible par
 * référence + jeton, y compris sans compte.
 */

import Link from 'next/link';
import { useCallback, useEffect, useState } from 'react';

import { SurfaceBadge, SurfaceCard, SurfaceNotice } from '../../kernel/components/SurfacePrimitives';
import type { SurfaceIdentity } from '../../kernel/domain/surface-identity';
import { restoreSession } from '../application/load-buyer-session';
import { canReview, type AccountOrder, type BuyerSession } from '../domain/buyer';
import { formatPrice } from '../domain/product';
import { fetchBuyerOrders } from '../infrastructure/account-gateway';

export function BuyerOrdersSurface({ identity }: { identity: SurfaceIdentity }) {
  const [ready, setReady] = useState(false);
  const [session, setSession] = useState<BuyerSession | null>(null);
  const [orders, setOrders] = useState<AccountOrder[] | null>(null);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setError(null);

    const result = await fetchBuyerOrders();

    if (result === null) {
      setError('Impossible de charger vos commandes pour le moment.');

      return;
    }

    setOrders(result);
  }, []);

  useEffect(() => {
    let cancelled = false;

    void restoreSession().then((restored) => {
      if (cancelled) {
        return;
      }

      setSession(restored);
      setReady(true);

      if (restored !== null) {
        void load();
      }
    });

    return () => {
      cancelled = true;
    };
  }, [load]);

  if (!ready) {
    return <SurfaceNotice identity={identity}>Chargement de vos commandes…</SurfaceNotice>;
  }

  if (session === null) {
    return (
      <SurfaceNotice identity={identity}>
        Connectez-vous pour retrouver vos commandes. <Link href="/compte">Se connecter</Link>
      </SurfaceNotice>
    );
  }

  if (error !== null) {
    return (
      <SurfaceNotice identity={identity} alert>
        {error}
      </SurfaceNotice>
    );
  }

  if (orders === null) {
    return <SurfaceNotice identity={identity}>Chargement de vos commandes…</SurfaceNotice>;
  }

  if (orders.length === 0) {
    return (
      <SurfaceNotice identity={identity}>
        Aucune commande pour l&apos;instant. <Link href="/produits">Découvrir les produits</Link>
      </SurfaceNotice>
    );
  }

  return (
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
            <SurfaceBadge identity={identity} tone={canReview(order.status) ? 'accent' : 'neutral'}>
              {order.status}
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
              Suivre
            </Link>
            {canReview(order.status) ? (
              <Link
                href={`/suivi?reference=${encodeURIComponent(order.reference)}&token=${encodeURIComponent(order.trackingToken)}#avis`}
                style={{ color: identity.palette.primary, fontWeight: 600 }}
              >
                Laisser un avis
              </Link>
            ) : null}
          </div>
        </SurfaceCard>
      ))}
    </ul>
  );
}
