'use client';

/**
 * Retour de paiement — verticale Retail (#9627).
 *
 * Repris de `front/marketplace/src/app/paiement/retour/page.tsx` (#7812).
 *
 * ## Pourquoi cette page polle
 *
 * Le statut « payé » n'arrive **pas** par la redirection du PSP : il arrive par
 * le webhook signé côté serveur, qui peut prendre quelques secondes. La page
 * interroge donc le suivi public et réessaie, plutôt que d'afficher un échec
 * qui serait faux.
 *
 * ## Le jeton ne sort jamais du navigateur
 *
 * Le PSP ne reçoit que la référence. Le jeton de suivi est relu depuis le
 * stockage local — c'est ce qui permet de vérifier le paiement sans compte.
 */

import Link from 'next/link';
import { useCallback, useEffect, useState } from 'react';

import { RETAIL_PAYMENT_COPY as C } from '../../copy/retail-copy';
import { SurfaceCard, SurfaceNotice } from '../../kernel/components/SurfacePrimitives';
import type { SurfaceIdentity } from '../../kernel/domain/surface-identity';
import { formatPrice } from '../../kernel/domain/money';
import type { SavedOrder } from '../domain/checkout';
import { findSavedOrder } from '../infrastructure/order-storage';
import { loadOrderTracking } from '../infrastructure/market-gateway';

/** Intervalle de vérification (ms). */
export const PAYMENT_POLL_INTERVAL_MS = 5_000;

/** Nombre de vérifications avant de proposer une relance manuelle (~1 min). */
export const PAYMENT_MAX_POLLS = 12;

type PaymentState =
  | 'loading'
  | 'paid'
  | 'pending'
  | 'refunded'
  | 'timeout'
  | 'error'
  | 'unknown-order';

export function PaymentReturnSurface({
  identity,
  reference,
}: {
  identity: SurfaceIdentity;
  reference: string;
}) {
  const [state, setState] = useState<PaymentState>('loading');
  const [order, setOrder] = useState<SavedOrder | null>(null);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [attempt, setAttempt] = useState(0);

  const check = useCallback(async (saved: SavedOrder) => {
    const tracking = await loadOrderTracking(saved.reference, saved.trackingToken);

    if (tracking === null) {
      return { status: 'error' as const };
    }

    return { status: tracking.paymentStatus };
  }, []);

  useEffect(() => {
    if (reference.trim() === '') {
      setState('unknown-order');

      return;
    }

    const saved = findSavedOrder(reference.trim());

    if (saved === null) {
      // Jeton absent de ce navigateur (autre appareil, stockage purgé) : le
      // suivi manuel référence + jeton reste possible.
      setState('unknown-order');

      return;
    }

    setOrder(saved);

    let cancelled = false;
    let polls = 0;
    let timer: ReturnType<typeof setTimeout> | null = null;

    const run = async () => {
      try {
        const result = await check(saved);

        if (cancelled) {
          return;
        }

        if (result.status === 'error') {
          setErrorMessage(C.verifyFailed);
          setState('error');

          return;
        }

        if (result.status === 'paid') {
          setState('paid');

          return;
        }

        if (result.status === 'refunded') {
          setState('refunded');

          return;
        }

        polls += 1;

        if (polls >= PAYMENT_MAX_POLLS) {
          setState('timeout');

          return;
        }

        setState('pending');
        timer = setTimeout(() => void run(), PAYMENT_POLL_INTERVAL_MS);
      } catch {
        if (!cancelled) {
          setErrorMessage(C.verifyFailed);
          setState('error');
        }
      }
    };

    void run();

    return () => {
      cancelled = true;

      if (timer !== null) {
        clearTimeout(timer);
      }
    };
  }, [reference, attempt, check]);

  const retry = () => {
    setState('loading');
    setAttempt((value) => value + 1);
  };

  const trackingHref =
    order !== null
      ? `/suivi?reference=${encodeURIComponent(order.reference)}&token=${encodeURIComponent(order.trackingToken)}`
      : '/suivi';

  if (state === 'loading') {
    return <SurfaceNotice identity={identity}>Vérification de votre paiement…</SurfaceNotice>;
  }

  if (state === 'unknown-order') {
    return (
      <SurfaceNotice identity={identity}>
        Nous ne retrouvons pas cette commande dans ce navigateur. Utilisez le suivi avec votre
        référence et votre jeton. <Link href="/suivi">Suivre ma commande</Link>
      </SurfaceNotice>
    );
  }

  const messages: Record<Exclude<PaymentState, 'loading' | 'unknown-order'>, [string, string]> = {
    paid: [
      C.paidTitle,
      C.paidBody,
    ],
    pending: [
      C.pendingTitle,
      C.pendingBody,
    ],
    timeout: [
      C.stillPendingTitle,
      C.stillPendingBody,
    ],
    refunded: [
      C.refundedTitle,
      C.refundedBody,
    ],
    error: [C.errorTitle, errorMessage ?? C.errorBody],
  };

  const [title, body] = messages[state];

  return (
    <div style={{ display: 'grid', gap: '1rem' }}>
      <SurfaceCard identity={identity}>
        <h2 style={{ margin: 0, fontSize: '1.05rem', fontWeight: 800 }}>{title}</h2>
        <p style={{ margin: '0.3rem 0 0', fontSize: '0.88rem', color: '#57534E' }}>{body}</p>

        {state === 'timeout' || state === 'error' ? (
          <button
            type="button"
            onClick={retry}
            style={{
              marginTop: '0.6rem',
              padding: '0.5rem 1rem',
              borderRadius: '999px',
              border: '1px solid #D6D3D1',
              background: '#FFFFFF',
              fontSize: '0.82rem',
              fontWeight: 600,
              cursor: 'pointer',
            }}
          >
            Revérifier
          </button>
        ) : null}

        {order !== null ? (
          <dl
            style={{
              margin: '0.8rem 0 0',
              paddingTop: '0.6rem',
              borderTop: '1px solid #F5F5F4',
              fontSize: '0.82rem',
              display: 'grid',
              gap: '0.25rem',
            }}
          >
            <div>
              <dt style={{ display: 'inline', color: '#78716C' }}>Boutique : </dt>
              <dd style={{ display: 'inline', margin: 0 }}>{order.sellerName}</dd>
            </div>
            <div>
              <dt style={{ display: 'inline', color: '#78716C' }}>Référence : </dt>
              <dd style={{ display: 'inline', margin: 0, fontFamily: 'monospace' }}>
                {order.reference}
              </dd>
            </div>
            <div>
              <dt style={{ display: 'inline', color: '#78716C' }}>Montant : </dt>
              <dd style={{ display: 'inline', margin: 0, fontWeight: 700 }}>
                {formatPrice(order.totalMinor, order.currency) ?? '—'}
              </dd>
            </div>
          </dl>
        ) : null}
      </SurfaceCard>

      <div style={{ display: 'flex', gap: '0.6rem' }}>
        <Link
          href={trackingHref}
          style={{
            padding: '0.6rem 1.1rem',
            borderRadius: '999px',
            background: identity.palette.primary,
            color: identity.palette.onPrimary,
            fontSize: '0.85rem',
            fontWeight: 700,
            textDecoration: 'none',
          }}
        >
          Suivre ma commande
        </Link>
        <Link
          href="/produits"
          style={{
            padding: '0.6rem 1.1rem',
            borderRadius: '999px',
            border: '1px solid #D6D3D1',
            background: '#FFFFFF',
            color: '#1C1917',
            fontSize: '0.85rem',
            fontWeight: 600,
            textDecoration: 'none',
          }}
        >
          Continuer mes achats
        </Link>
      </div>
    </div>
  );
}
