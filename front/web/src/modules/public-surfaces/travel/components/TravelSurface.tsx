import Link from 'next/link';

import {
  SurfaceBadge,
  SurfaceCard,
  SurfaceCta,
  SurfaceHero,
  SurfaceMeta,
  SurfaceNotice,
  SurfaceShell,
  SurfaceTenantInvitation,
  type SurfaceIdentity,
} from '../../kernel';
import { getSurfaceCopy } from '../../copy/surfaces-copy';
import type { TripCard } from '../application/load-travel-surface';
import { hasRoute, tripRoute } from '../domain/trip';

/**
 * Rendu de la vitrine Billets (#9594).
 *
 * ## Composition propre à la verticale
 *
 * Un billet se choisit **sur l'itinéraire et l'horaire**, pas sur une vignette.
 * D'où :
 *
 *  1. **Liste en lignes, pas en grille** : l'œil balaie verticalement des
 *     itinéraires — c'est le seul format qui rende les départs comparables.
 *  2. **L'itinéraire est le titre**, l'horaire la métadonnée, le prix et la
 *     disponibilité alignés à droite : lecture en trois colonnes mentales.
 *  3. **La disponibilité est un badge** (`Réservable` / `Complet`), et un
 *     trajet sans places connues n'est jamais déclaré complet — règle portée
 *     par `isBookable()` dans le domaine.
 *
 * Composant pur : aucun fetch, aucun état.
 */
export function TravelSurface({
  identity,
  cards,
  failed,
  locale,
}: {
  identity: SurfaceIdentity;
  cards: readonly TripCard[];
  failed: boolean;
  locale?: string;
}) {
  const copy = getSurfaceCopy(locale);
  const shared = copy.shared;

  return (
    <SurfaceShell identity={identity}>
      <SurfaceHero
        identity={identity}
        eyebrow={copy.verticals.travel.eyebrow}
        action={
          <SurfaceCta identity={identity} href="/decouvrir">
            {shared.allDestinations}
          </SurfaceCta>
        }
      />

      <main style={{ maxWidth: '64rem', margin: '0 auto', padding: '2rem 1.25rem 4rem' }}>
        {failed ? (
          <SurfaceNotice identity={identity} alert>
            {shared.unavailable}
          </SurfaceNotice>
        ) : null}

        {!failed && cards.length === 0 ? (
          <SurfaceNotice identity={identity}>{shared.empty}</SurfaceNotice>
        ) : null}

        {!failed && cards.length > 0 ? (
          <ul
            style={{
              listStyle: 'none',
              margin: 0,
              padding: 0,
              display: 'grid',
              gap: '0.75rem',
            }}
          >
            {cards.map(({ trip, priceLabel, seats, bookable }) => (
              <SurfaceCard key={trip.reference} as="li" identity={identity}>
                <div
                  style={{
                    display: 'flex',
                    flexWrap: 'wrap',
                    gap: '0.75rem',
                    alignItems: 'center',
                    justifyContent: 'space-between',
                  }}
                >
                  <div style={{ minWidth: '12rem' }}>
                    <strong style={{ display: 'block', fontSize: '1rem' }}>
                      {hasRoute(trip) ? tripRoute(trip) : shared.trip}
                    </strong>
                    <SurfaceMeta identity={identity}>
                      {[trip.departureAt, seats].filter(Boolean).join(' · ') ||
                        shared.scheduleTbc}
                    </SurfaceMeta>
                  </div>

                  <div
                    style={{
                      display: 'flex',
                      alignItems: 'center',
                      gap: '0.75rem',
                      marginLeft: 'auto',
                    }}
                  >
                    <strong style={{ fontSize: '1.05rem' }}>
                      {priceLabel ?? shared.priceOnRequest}
                    </strong>

                    <SurfaceBadge identity={identity} tone={bookable ? 'accent' : 'neutral'}>
                      {bookable ? shared.bookable : shared.soldOut}
                    </SurfaceBadge>
                  </div>
                </div>
              </SurfaceCard>
            ))}
          </ul>
        ) : null}
        <SurfaceTenantInvitation identity={identity} locale={locale} />
      </main>
    </SurfaceShell>
  );
}
