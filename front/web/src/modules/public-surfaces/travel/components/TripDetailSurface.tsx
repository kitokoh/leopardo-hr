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
import type { TripDetailView } from '../application/load-trip-detail';
import { LEGEND_ORDER, seatStatusLabel, seatStatusSymbol } from '../domain/seat-layout';

/**
 * Fiche trajet de la vitrine Billets (#9628).
 *
 * Repris de `front/travel-web/src/app/trips/[id]/page.tsx` et de
 * `components/seat-map.tsx`. Composition propre : l'itinéraire et l'horaire
 * dominent, le plan de sièges est un **tableau de bordées** — pas une grille
 * de cartes.
 *
 * ## Accessibilité du plan
 *
 * Chaque siège porte un symbole ET un libellé texte : l'état n'est jamais
 * porté par la seule couleur. C'est une règle du domaine (`seatStatusSymbol`,
 * `seatStatusLabel`), pas une intention de design.
 *
 * Composant pur : aucun fetch, aucun état.
 */
export function TripDetailSurface({
  identity,
  view,
  locale,
}: {
  identity: SurfaceIdentity;
  view: TripDetailView;
  locale?: string;
}) {
  const copy = getSurfaceCopy(locale);
  const shared = copy.shared;
  const { trip, route, priceLabel, seats, bookable, seatMap, layoutConfigured } = view;

  return (
    <SurfaceShell identity={identity}>
      <main style={{ maxWidth: '56rem', margin: '0 auto', padding: '2.5rem 1.25rem 4rem' }}>
        <Link
          href="/billets"
          style={{
            display: 'inline-block',
            marginBottom: '1.25rem',
            fontSize: '0.85rem',
            color: identity.palette.muted,
            textDecoration: 'none',
          }}
        >
          ← {shared.allDestinations}
        </Link>

        <SurfaceCard identity={identity}>
          <div
            style={{
              display: 'flex',
              flexWrap: 'wrap',
              gap: '1rem',
              alignItems: 'baseline',
              justifyContent: 'space-between',
            }}
          >
            <div>
              <h1 style={{ margin: 0, fontSize: 'clamp(1.3rem, 3vw, 1.8rem)' }}>{route}</h1>
              <SurfaceMeta identity={identity}>
                {[trip.departureAt, trip.arrivalAt].filter(Boolean).join(' → ') ||
                  shared.scheduleTbc}
              </SurfaceMeta>
            </div>

            <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem' }}>
              <strong style={{ fontSize: '1.3rem' }}>
                {priceLabel ?? shared.priceOnRequest}
              </strong>
              <SurfaceBadge identity={identity} tone={bookable ? 'accent' : 'neutral'}>
                {bookable ? (seats ?? shared.bookable) : shared.soldOut}
              </SurfaceBadge>
            </div>
          </div>
        </SurfaceCard>

        {trip.seats.length === 0 ? (
          <div style={{ marginTop: '1.25rem' }}>
            <SurfaceNotice identity={identity}>{shared.scheduleTbc}</SurfaceNotice>
          </div>
        ) : (
          <div style={{ marginTop: '1.5rem' }}>
            <h2
              style={{
                margin: '0 0 0.75rem',
                fontSize: '1rem',
                fontWeight: 800,
                color: identity.palette.onSurface,
              }}
            >
              {shared.place} / {shared.places}
            </h2>

            <div
              style={{
                display: 'grid',
                gap: '0.4rem',
                padding: '1rem',
                borderRadius: '0.9rem',
                border: `1px solid ${identity.palette.muted}33`,
                background: '#FFFFFF',
              }}
            >
              {seatMap.rows.map((row) => (
                <div
                  key={row.rowNumber}
                  style={{ display: 'flex', gap: '0.35rem', alignItems: 'center' }}
                >
                  <span
                    style={{
                      width: '1.5rem',
                      fontSize: '0.7rem',
                      color: identity.palette.muted,
                      fontVariantNumeric: 'tabular-nums',
                    }}
                  >
                    {row.rowNumber}
                  </span>

                  {row.cells.map((cell, index) =>
                    cell.status === 'aisle' ? (
                      <span
                        key={`aisle-${row.rowNumber}-${index}`}
                        aria-hidden
                        style={{ width: '0.9rem' }}
                      />
                    ) : (
                      <span
                        key={`seat-${cell.seatNumber}`}
                        title={cell.label}
                        aria-label={
                          cell.seatNumber !== null
                            ? `Siège ${cell.seatNumber}, ${cell.label}`
                            : cell.label
                        }
                        style={{
                          display: 'inline-flex',
                          alignItems: 'center',
                          justifyContent: 'center',
                          width: '1.9rem',
                          height: '1.9rem',
                          borderRadius: '0.4rem',
                          fontSize: '0.7rem',
                          fontWeight: 700,
                          border: `1px solid ${identity.palette.muted}44`,
                          background:
                            cell.status === 'free'
                              ? `${identity.palette.accent}18`
                              : `${identity.palette.muted}14`,
                          color:
                            cell.status === 'free'
                              ? identity.palette.primary
                              : identity.palette.muted,
                        }}
                      >
                        {cell.seatNumber}
                        {cell.symbol ? (
                          <span aria-hidden style={{ marginLeft: '0.1rem', fontSize: '0.6rem' }}>
                            {cell.symbol}
                          </span>
                        ) : null}
                      </span>
                    ),
                  )}
                </div>
              ))}
            </div>

            {/* Légende : symbole + libellé, jamais la couleur seule. */}
            <ul
              style={{
                listStyle: 'none',
                margin: '0.75rem 0 0',
                padding: 0,
                display: 'flex',
                flexWrap: 'wrap',
                gap: '0.75rem',
                fontSize: '0.75rem',
                color: identity.palette.muted,
              }}
            >
              {LEGEND_ORDER.map((status) => (
                <li key={status} style={{ display: 'flex', alignItems: 'center', gap: '0.3rem' }}>
                  <span aria-hidden>{seatStatusSymbol(status) || '·'}</span>
                  {seatStatusLabel(status)}
                </li>
              ))}
            </ul>

            {!layoutConfigured ? (
              <p style={{ margin: '0.6rem 0 0', fontSize: '0.75rem', color: identity.palette.muted }}>
                {shared.scheduleTbc}
              </p>
            ) : null}
          </div>
        )}

        <div style={{ marginTop: '1.5rem' }}>
          {bookable ? (
            <SurfaceCta identity={identity} href={`/billets/reservation?trip=${trip.reference}`}>
              {shared.bookable}
            </SurfaceCta>
          ) : (
            <SurfaceNotice identity={identity}>{shared.soldOut}</SurfaceNotice>
          )}
        </div>
      </main>
    </SurfaceShell>
  );
}
