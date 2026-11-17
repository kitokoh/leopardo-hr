import Link from 'next/link';

import { SurfaceHero, SurfaceShell, type SurfaceIdentity } from '../../kernel';
import { getSurfaceCopy } from '../../copy/surfaces-copy';
import type { TripCard } from '../application/load-travel-surface';
import { hasRoute, tripRoute } from '../domain/trip';

/**
 * Rendu de la vitrine Billets.
 *
 * Composant pur : reçoit des trajets déjà chargés. Aucun fetch, aucun état.
 * Toute chaîne visible vient du catalogue de copie.
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
            {shared.allDestinations}
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
              <li
                key={trip.reference}
                style={{
                  border: `1px solid ${identity.palette.muted}33`,
                  borderRadius: '0.75rem',
                  padding: '1rem',
                  background: '#FFFFFF',
                  display: 'flex',
                  flexWrap: 'wrap',
                  gap: '0.75rem',
                  alignItems: 'center',
                  justifyContent: 'space-between',
                }}
              >
                <div>
                  <strong style={{ display: 'block' }}>
                    {hasRoute(trip) ? tripRoute(trip) : shared.trip}
                  </strong>
                  <span style={{ color: identity.palette.muted, fontSize: '0.875rem' }}>
                    {[trip.departureAt, seats].filter(Boolean).join(' · ') ||
                      shared.scheduleTbc}
                  </span>
                </div>

                <div style={{ textAlign: 'right' }}>
                  <strong style={{ display: 'block' }}>
                    {priceLabel ?? shared.priceOnRequest}
                  </strong>
                  <span style={{ color: identity.palette.muted, fontSize: '0.8rem' }}>
                    {bookable ? shared.bookable : shared.soldOut}
                  </span>
                </div>
              </li>
            ))}
          </ul>
        ) : null}
      </main>
    </SurfaceShell>
  );
}
