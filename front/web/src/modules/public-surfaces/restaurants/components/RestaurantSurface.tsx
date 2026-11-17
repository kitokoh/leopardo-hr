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
  type SurfaceIdentity,
} from '../../kernel';
import { getSurfaceCopy } from '../../copy/surfaces-copy';
import type { RestaurantCard } from '../application/load-restaurant-surface';
import { formatDistance } from '../domain/restaurant';

/**
 * Rendu de la vitrine Restaurant (#9594).
 *
 * ## Composition propre à la verticale
 *
 * Le restaurant se choisit **sur la note et la proximité**. D'où :
 *
 *  1. **Grille moyenne** (`min 17rem`) : assez dense pour comparer, assez large
 *     pour que le nom respire.
 *  2. **La note est un badge accent** en tête de carte, la distance en
 *     métadonnée : ce sont les deux critères de tri réels du public.
 *  3. **Sans avis, aucun badge de note** — jamais « ★ 0 » : une adresse pas
 *     encore évaluée n'est pas une mauvaise adresse.
 *
 * Composant pur : il reçoit des cartes déjà chargées et triées. Aucun fetch,
 * aucun état, aucune règle métier — testable sans réseau.
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
        action={<SurfaceCta identity={identity} href="/decouvrir">{shared.allAddresses}</SurfaceCta>}
      />

      <main style={{ maxWidth: '72rem', margin: '0 auto', padding: '2rem 1.25rem 4rem' }}>
        {failed ? (
          <SurfaceNotice identity={identity} alert>
            {shared.unavailable}
          </SurfaceNotice>
        ) : null}

        {!failed && cards.length === 0 ? (
          <SurfaceNotice identity={identity}>{shared.empty}</SurfaceNotice>
        ) : null}

        {!failed && cards.length > 0 ? (
          <>
            <SurfaceMeta identity={identity}>
              {total} {total > 1 ? shared.products : shared.product}
            </SurfaceMeta>

            <div style={{ marginTop: '1rem' }}>
              <SurfaceGrid min="17rem">
                {cards.map(({ restaurant, rating }) => {
                  const distance = formatDistance(restaurant.distanceKm);

                  return (
                    <SurfaceCard key={restaurant.slug} as="li" interactive identity={identity}>
                      <Link
                        href={`${identity.basePath}/${restaurant.slug}`}
                        style={{ color: 'inherit', textDecoration: 'none' }}
                      >
                        <div
                          style={{
                            display: 'flex',
                            alignItems: 'baseline',
                            justifyContent: 'space-between',
                            gap: '0.5rem',
                          }}
                        >
                          <h2 style={{ margin: 0, fontSize: '1.05rem', lineHeight: 1.25 }}>
                            {restaurant.name}
                          </h2>

                          {rating !== null ? (
                            <SurfaceBadge identity={identity} tone="accent">
                              ★ {rating}
                            </SurfaceBadge>
                          ) : null}
                        </div>

                        <SurfaceMeta identity={identity}>
                          {[restaurant.kind, restaurant.city].filter(Boolean).join(' · ') ||
                            shared.address}
                        </SurfaceMeta>

                        <SurfaceMeta identity={identity}>
                          {rating !== null
                            ? `${restaurant.reviewsCount} ${shared.reviews}`
                            : shared.noReviews}
                          {distance !== null ? ` · ${distance}` : ''}
                        </SurfaceMeta>
                      </Link>
                    </SurfaceCard>
                  );
                })}
              </SurfaceGrid>
            </div>
          </>
        ) : null}
      </main>
    </SurfaceShell>
  );
}
