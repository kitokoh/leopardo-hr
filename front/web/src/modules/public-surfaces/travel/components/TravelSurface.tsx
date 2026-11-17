import Link from 'next/link';

import { SurfaceHero, SurfaceShell, type SurfaceIdentity } from '../../kernel';
import type { TripCard } from '../application/load-travel-surface';
import { tripRoute } from '../domain/trip';

/**
 * Rendu de la vitrine Billets.
 *
 * Composant pur : reçoit des trajets déjà chargés. Aucun fetch, aucun état.
 */
export function TravelSurface({
  identity,
  cards,
  failed,
}: {
  identity: SurfaceIdentity;
  cards: readonly TripCard[];
  failed: boolean;
}) {
  return (
    <SurfaceShell identity={identity}>
      <SurfaceHero
        identity={identity}
        eyebrow="Billetterie"
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
            Explorer les destinations
          </Link>
        }
      />

      <main style={{ maxWidth: '72rem', margin: '0 auto', padding: '2rem 1.25rem 4rem' }}>
        {failed ? (
          <p role="alert" style={{ color: identity.palette.muted }}>
            La billetterie est momentanément indisponible. Réessayez dans un instant.
          </p>
        ) : null}

        {!failed && cards.length === 0 ? (
          <p style={{ color: identity.palette.muted }}>
            Aucun trajet ne correspond à votre recherche.
          </p>
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
                  <strong style={{ display: 'block' }}>{tripRoute(trip)}</strong>
                  <span style={{ color: identity.palette.muted, fontSize: '0.875rem' }}>
                    {[trip.departureAt, seats].filter(Boolean).join(' · ') || 'Horaires à confirmer'}
                  </span>
                </div>

                <div style={{ textAlign: 'right' }}>
                  <strong style={{ display: 'block' }}>
                    {priceLabel ?? 'Prix sur demande'}
                  </strong>
                  <span style={{ color: identity.palette.muted, fontSize: '0.8rem' }}>
                    {bookable ? 'Réservable' : 'Complet'}
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
