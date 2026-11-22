import Link from 'next/link';

import {
  SurfaceBadge,
  SurfaceCard,
  SurfaceCta,
  SurfaceMeta,
  SurfaceNotice,
  SurfaceShell,
  type SurfaceIdentity,
} from '../../kernel';
import { getSurfaceCopy } from '../../copy/surfaces-copy';
import type { ProductCard } from '../application/load-catalog';

/**
 * Fiche produit de la vitrine Retail (#9627).
 *
 * Repris de `front/marketplace/src/app/produits/[id]/page.tsx`. Composition
 * propre : le produit est **seul** sur la page, donc le prix et l'action
 * prennent toute la largeur utile — l'inverse de la grille de catalogue.
 *
 * Composant pur : aucun fetch, aucun état. L'ajout au panier est un îlot
 * client séparé (`AddToCartButton`), pour que la fiche reste rendue côté
 * serveur et indexable.
 */
export function ProductSurface({
  identity,
  card,
  locale,
}: {
  identity: SurfaceIdentity;
  card: ProductCard;
  locale?: string;
}) {
  const copy = getSurfaceCopy(locale);
  const shared = copy.shared;
  const { product, priceLabel, rating, purchasable } = card;

  return (
    <SurfaceShell identity={identity}>
      <main style={{ maxWidth: '56rem', margin: '0 auto', padding: '2.5rem 1.25rem 4rem' }}>
        <Link
          href="/produits"
          style={{
            display: 'inline-flex',
            alignItems: 'center',
            gap: '0.35rem',
            marginBottom: '1.5rem',
            fontSize: '0.85rem',
            color: identity.palette.muted,
            textDecoration: 'none',
            transition: 'color 0.15s ease',
          }}
        >
          ← {shared.allShops}
        </Link>

        <SurfaceCard identity={identity}>
          <div style={{ display: 'flex', flexWrap: 'wrap', gap: '2rem' }}>
            <div style={{ flex: '1 1 18rem', minWidth: '14rem' }}>
              <h1
                style={{
                  margin: 0,
                  fontSize: 'clamp(1.4rem, 3vw, 1.9rem)',
                  lineHeight: 1.2,
                  letterSpacing: '-0.02em',
                  fontWeight: 800,
                }}
              >
                {product.name}
              </h1>

              <SurfaceMeta identity={identity}>
                {[product.seller.name, product.seller.city].filter(Boolean).join(' · ') ||
                  shared.shop}
              </SurfaceMeta>

              {rating !== null ? (
                <p
                  style={{
                    margin: '0.6rem 0 0',
                    fontSize: '0.85rem',
                    color: identity.palette.accent,
                    fontWeight: 600,
                  }}
                >
                  ★ {rating} / 5 · {product.ratingCount} {shared.reviews}
                </p>
              ) : (
                <p style={{ margin: '0.5rem 0 0', fontSize: '0.85rem', color: identity.palette.muted }}>
                  {shared.noReviews}
                </p>
              )}

              {product.description ? (
                <p
                  style={{
                    margin: '1.25rem 0 0',
                    lineHeight: 1.65,
                    fontSize: '0.95rem',
                    color: identity.palette.muted,
                  }}
                >
                  {product.description}
                </p>
              ) : null}
            </div>

            <div
              style={{
                flex: '0 0 16rem',
                display: 'flex',
                flexDirection: 'column',
                gap: '0.85rem',
                justifyContent: 'flex-start',
                padding: '1.5rem',
                borderRadius: '0.75rem',
                background: `${identity.palette.primary}06`,
                border: `1px solid ${identity.palette.muted}15`,
              }}
            >
              <strong
                style={{
                  fontSize: 'clamp(1.4rem, 3vw, 1.8rem)',
                  letterSpacing: '-0.02em',
                  fontWeight: 800,
                }}
              >
                {priceLabel ?? shared.priceOnRequest}
              </strong>

              <SurfaceBadge identity={identity} tone={purchasable ? 'accent' : 'neutral'}>
                {purchasable ? shared.bookable : shared.soldOut}
              </SurfaceBadge>

              {purchasable ? (
                <SurfaceCta identity={identity} href={`/panier?add=${product.id}`}>
                  {shared.product}
                </SurfaceCta>
              ) : (
                <SurfaceNotice identity={identity}>{shared.soldOut}</SurfaceNotice>
              )}
            </div>
          </div>
        </SurfaceCard>
      </main>
    </SurfaceShell>
  );
}
