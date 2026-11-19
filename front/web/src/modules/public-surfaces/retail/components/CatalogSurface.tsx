import Link from 'next/link';

import {
  SurfaceBadge,
  SurfaceCard,
  SurfaceCta,
  SurfaceGrid,
  SurfaceHero,
  SurfaceMeta,
  SurfaceNotice,
  SurfaceShell,
  SurfaceTenantInvitation,
  type SurfaceIdentity,
} from '../../kernel';
import { getSurfaceCopy } from '../../copy/surfaces-copy';
import type { CatalogResult } from '../application/load-catalog';

/**
 * Catalogue de la vitrine Retail (#9627).
 *
 * Repris de `front/marketplace/src/app/produits/page.tsx`. Composition propre
 * à la verticale : grille dense, le **prix** est le signal principal (on
 * compare des prix), le vendeur une métadonnée discrète.
 *
 * Composant pur : aucun fetch, aucun état.
 */
export function CatalogSurface({
  identity,
  result,
  locale,
}: {
  identity: SurfaceIdentity;
  result: CatalogResult;
  locale?: string;
}) {
  const copy = getSurfaceCopy(locale);
  const shared = copy.shared;

  return (
    <SurfaceShell identity={identity}>
      <SurfaceHero
        identity={identity}
        eyebrow={copy.verticals.retail.eyebrow}
        action={
          <SurfaceCta identity={identity} href="/boutiques">
            {shared.allShops}
          </SurfaceCta>
        }
      />

      <main style={{ maxWidth: '72rem', margin: '0 auto', padding: '2rem 1.25rem 4rem' }}>
        {result.failed ? (
          <SurfaceNotice identity={identity} alert>
            {shared.unavailable}
          </SurfaceNotice>
        ) : null}

        {!result.failed && result.cards.length === 0 ? (
          <SurfaceNotice identity={identity}>{shared.empty}</SurfaceNotice>
        ) : null}

        {!result.failed && result.cards.length > 0 ? (
          <SurfaceGrid min="14rem">
            {result.cards.map(({ product, priceLabel, rating, purchasable }) => (
              <SurfaceCard key={product.id} as="li" interactive identity={identity}>
                <Link href={`/produits/${product.id}`} data-surface-card-link="">
                  <strong style={{ fontSize: '0.98rem', lineHeight: 1.3 }}>
                    {product.name}
                  </strong>

                  <SurfaceMeta identity={identity}>
                    {[product.seller.name, product.seller.city].filter(Boolean).join(' · ') ||
                      shared.shop}
                  </SurfaceMeta>

                  <div
                    style={{
                      display: 'flex',
                      alignItems: 'center',
                      justifyContent: 'space-between',
                      gap: '0.5rem',
                      marginTop: '0.35rem',
                    }}
                  >
                    <strong style={{ fontSize: '1.05rem' }}>
                      {priceLabel ?? shared.priceOnRequest}
                    </strong>

                    {rating !== null ? (
                      <SurfaceMeta identity={identity}>
                        {rating} / 5 · {product.ratingCount} {shared.reviews}
                      </SurfaceMeta>
                    ) : null}
                  </div>

                  {!purchasable ? (
                    <div>
                      <SurfaceBadge identity={identity}>{shared.soldOut}</SurfaceBadge>
                    </div>
                  ) : null}
                </Link>
              </SurfaceCard>
            ))}
          </SurfaceGrid>
        ) : null}

        <SurfaceTenantInvitation identity={identity} locale={locale} />
      </main>
    </SurfaceShell>
  );
}
