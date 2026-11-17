import Link from 'next/link';

import { SurfaceHero, SurfaceShell, type SurfaceIdentity } from '../../kernel';
import { getSurfaceCopy } from '../../copy/surfaces-copy';
import type { ShopView } from '../application/load-shop-view';

/**
 * Rendu de la vitrine Retail.
 *
 * Composant pur : reçoit une boutique déjà chargée. Aucun fetch, aucun état.
 * Toute chaîne visible vient du catalogue de copie.
 */
export function ShopSurface({
  identity,
  view,
  locale,
}: {
  identity: SurfaceIdentity;
  view: ShopView;
  locale?: string;
}) {
  const { shop, rating, catalogLabel } = view;
  const copy = getSurfaceCopy(locale);
  const shared = copy.shared;

  return (
    <SurfaceShell identity={identity}>
      <SurfaceHero
        identity={identity}
        eyebrow={shop.city ?? copy.verticals.retail.eyebrow}
        action={
          <Link
            href="/boutiques"
            style={{
              display: 'inline-block',
              padding: '0.7rem 1.2rem',
              borderRadius: '0.6rem',
              background: identity.palette.primary,
              color: identity.palette.onPrimary,
              fontWeight: 600,
              textDecoration: 'none',
            }}
          >
            {shared.allShops}
          </Link>
        }
      />

      <main style={{ maxWidth: '72rem', margin: '0 auto', padding: '2rem 1.25rem 4rem' }}>
        <h2 style={{ marginTop: 0, fontSize: '1.4rem' }}>{shop.name}</h2>

        <p style={{ color: identity.palette.muted }}>
          {catalogLabel}
          {rating !== null
            ? ` · ★ ${rating} (${shop.ratingCount})`
            : ` · ${shared.noReviews}`}
        </p>

        {shop.description ? <p>{shop.description}</p> : null}

        {shop.categories.length > 0 ? (
          <ul
            style={{
              listStyle: 'none',
              margin: '1.5rem 0 0',
              padding: 0,
              display: 'grid',
              gap: '0.75rem',
              gridTemplateColumns: 'repeat(auto-fill, minmax(14rem, 1fr))',
            }}
          >
            {shop.categories.map((category) => (
              <li
                key={category.name}
                style={{
                  border: `1px solid ${identity.palette.muted}33`,
                  borderRadius: '0.75rem',
                  padding: '0.9rem',
                  background: '#FFFFFF',
                }}
              >
                <strong style={{ display: 'block' }}>{category.name}</strong>
                <span style={{ color: identity.palette.muted, fontSize: '0.875rem' }}>
                  {category.productsCount}{' '}
                  {category.productsCount > 1 ? shared.products : shared.product}
                </span>
              </li>
            ))}
          </ul>
        ) : null}
      </main>
    </SurfaceShell>
  );
}
