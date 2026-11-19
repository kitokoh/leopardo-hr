'use client';

/**
 * Mes favoris — verticale Retail (#9627).
 *
 * Repris de `front/marketplace/src/app/compte/favoris/page.tsx`. Les produits
 * dépubliés sont filtrés côté serveur (fail-closed) : la vitrine n'a pas à
 * décider ce qui reste visible.
 */

import Link from 'next/link';
import { useCallback, useEffect, useState } from 'react';

import { SurfaceCard, SurfaceNotice } from '../../kernel/components/SurfacePrimitives';
import type { SurfaceIdentity } from '../../kernel/domain/surface-identity';
import { restoreSession } from '../application/load-buyer-session';
import type { BuyerSession } from '../domain/buyer';
import { formatPrice, type Product } from '../domain/product';
import { fetchFavorites, removeFavorite } from '../infrastructure/account-gateway';

export function BuyerFavoritesSurface({ identity }: { identity: SurfaceIdentity }) {
  const [ready, setReady] = useState(false);
  const [session, setSession] = useState<BuyerSession | null>(null);
  const [favorites, setFavorites] = useState<Product[] | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [removing, setRemoving] = useState<number | null>(null);

  const load = useCallback(async () => {
    setError(null);

    const result = await fetchFavorites();

    if (result === null) {
      setError('Impossible de charger vos favoris pour le moment.');

      return;
    }

    setFavorites(result);
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

  const remove = async (productId: number) => {
    if (removing !== null) {
      return;
    }

    setRemoving(productId);

    try {
      await removeFavorite(productId);
      setFavorites((current) =>
        current === null ? current : current.filter((product) => product.id !== productId),
      );
    } catch {
      setError('Le retrait a échoué — réessayez.');
    } finally {
      setRemoving(null);
    }
  };

  if (!ready) {
    return <SurfaceNotice identity={identity}>Chargement de vos favoris…</SurfaceNotice>;
  }

  if (session === null) {
    return (
      <SurfaceNotice identity={identity}>
        Connectez-vous pour retrouver vos favoris. <Link href="/compte">Se connecter</Link>
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

  if (favorites === null) {
    return <SurfaceNotice identity={identity}>Chargement de vos favoris…</SurfaceNotice>;
  }

  if (favorites.length === 0) {
    return (
      <SurfaceNotice identity={identity}>
        Aucun favori pour l&apos;instant — touchez le cœur sur un produit pour le retrouver ici.
      </SurfaceNotice>
    );
  }

  return (
    <ul style={{ listStyle: 'none', margin: 0, padding: 0, display: 'grid', gap: '0.75rem' }}>
      {favorites.map((product) => (
        <SurfaceCard key={product.id} identity={identity} as="li">
          <div style={{ display: 'flex', alignItems: 'center', gap: '0.9rem' }}>
            <div style={{ minWidth: 0, flex: 1 }}>
              <Link
                href={`/produits/${product.id}`}
                style={{ fontSize: '0.9rem', fontWeight: 700, color: '#1C1917' }}
              >
                {product.name}
              </Link>
              <p style={{ margin: '0.15rem 0 0', fontSize: '0.78rem', color: '#78716C' }}>
                {product.seller.name}
                {product.seller.city !== null ? ` · ${product.seller.city}` : ''}
              </p>
              <p style={{ margin: '0.25rem 0 0', fontSize: '0.9rem', fontWeight: 800 }}>
                {formatPrice(product.priceMinor, product.currency)}
                {!product.available ? (
                  <span style={{ marginLeft: '0.5rem', fontSize: '0.75rem', color: '#78716C' }}>
                    Épuisé
                  </span>
                ) : null}
              </p>
            </div>

            <button
              type="button"
              onClick={() => void remove(product.id)}
              disabled={removing === product.id}
              aria-label={`Retirer ${product.name} des favoris`}
              style={{
                border: 'none',
                background: 'transparent',
                color: '#E11D48',
                fontSize: '1.1rem',
                cursor: removing === product.id ? 'not-allowed' : 'pointer',
                opacity: removing === product.id ? 0.5 : 1,
              }}
            >
              ♥
            </button>
          </div>
        </SurfaceCard>
      ))}
    </ul>
  );
}
