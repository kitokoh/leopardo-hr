import Link from 'next/link';

import { SurfaceHero, SurfaceShell, type SurfaceIdentity } from '../../kernel';
import { getSurfaceCopy } from '../../copy/surfaces-copy';
import type { RestaurantCard } from '../application/load-restaurant-surface';
import { formatDistance } from '../domain/restaurant';

/**
 * Rendu de la vitrine Restaurant.
 *
 * Composant **pur** : il reçoit des cartes déjà chargées et triées. Aucun fetch,
 * aucun état, aucune règle métier — c'est ce qui le rend testable sans réseau et
 * réutilisable côté serveur comme côté client.
 *
 * Toute chaîne visible vient du catalogue de copie (`copy/surfaces-copy`), jamais
 * d'un littéral : la vitrine est servie en 4 langues.
 */
export function RestaurantSurface({
  identity,
  cards,
  failed,
  total,
  locale,
}: {
  identity: SurfaceIdentity;
  cards: readonly RestaurantCard[];
  failed: boolean;
  total: number;
  locale?: string;
}) {
  const copy = getSurfaceCopy(locale);
  const shared = copy.shared;

  return (
    <SurfaceShell identity={identity}>
      <SurfaceHero
        identity={identity}
        eyebrow={copy.verticals.restaurant.eyebrow}
        action={
          <Link
            href="/decouvrir"
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
            {shared.allAddresses}
          </Link>
        }
      />

      <main style={{ maxWidth: '72rem', margin: '0 auto', padding: '2rem 1.25rem 4rem' }}>
        {failed ? (
          <p role="alert" style={{ color: identity.palette.muted }}>
            {shared.unavailable}
          </p>
        ) : null}

        {!failed && cards.length === 0 ? (
          <p style={{ color: identity.palette.muted }}>{shared.empty}</p>
        ) : null}

        {!failed && cards.length > 0 ? (
          <>
            <p style={{ color: identity.palette.muted, marginTop: 0 }}>
              {total} {total > 1 ? shared.products : shared.product}
            </p>

            <ul
              style={{
                listStyle: 'none',
                margin: 0,
                padding: 0,
                display: 'grid',
                gap: '1rem',
                gridTemplateColumns: 'repeat(auto-fill, minmax(17rem, 1fr))',
              }}
            >
              {cards.map(({ restaurant, rating }) => {
                const distance = formatDistance(restaurant.distanceKm);

                return (
                  <li
                    key={restaurant.slug}
                    style={{
                      border: `1px solid ${identity.palette.muted}33`,
                      borderRadius: '0.75rem',
                      padding: '1rem',
                      background: '#FFFFFF',
                    }}
                  >
                    <Link
                      href={`${identity.basePath}/${restaurant.slug}`}
                      style={{ color: 'inherit', textDecoration: 'none' }}
                    >
                      <h2 style={{ margin: 0, fontSize: '1.05rem' }}>{restaurant.name}</h2>

                      <p style={{ margin: '0.35rem 0 0', color: identity.palette.muted, fontSize: '0.875rem' }}>
                        {[restaurant.kind, restaurant.city].filter(Boolean).join(' · ') || shared.address}
                      </p>

                      <p style={{ margin: '0.5rem 0 0', fontSize: '0.875rem' }}>
                        {rating !== null
                          ? `★ ${rating} (${restaurant.reviewsCount})`
                          : shared.noReviews}
                        {distance !== null ? ` · ${distance}` : ''}
                      </p>
                    </Link>
                  </li>
                );
              })}
            </ul>
          </>
        ) : null}
      </main>
    </SurfaceShell>
  );
}
