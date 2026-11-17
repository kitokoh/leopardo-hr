import Link from 'next/link';

import { SurfaceHero, SurfaceShell, type SurfaceIdentity } from '../../kernel';
import { getSurfaceCopy } from '../../copy/surfaces-copy';
import type { PropertyView } from '../application/load-property-view';
import { formatMoney } from '../domain/property';

/**
 * Rendu de la vitrine Hôtellerie.
 *
 * Composant pur : reçoit un hébergement déjà chargé. Aucun fetch, aucun état.
 * Toute chaîne visible vient du catalogue de copie.
 */
export function PropertySurface({
  identity,
  view,
  locale,
}: {
  identity: SurfaceIdentity;
  view: PropertyView;
  locale?: string;
}) {
  const { property, priceLabel, stars } = view;
  const copy = getSurfaceCopy(locale);
  const shared = copy.shared;

  return (
    <SurfaceShell identity={identity}>
      <SurfaceHero
        identity={identity}
        eyebrow={
          [property.city, property.country].filter(Boolean).join(', ') ||
          copy.verticals.hospitality.eyebrow
        }
        action={
          <Link
            href="/stay"
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
            {shared.allStays}
          </Link>
        }
      />

      <main style={{ maxWidth: '72rem', margin: '0 auto', padding: '2rem 1.25rem 4rem' }}>
        <h2 style={{ marginTop: 0, fontSize: '1.4rem' }}>{property.name}</h2>

        <p style={{ color: identity.palette.muted }}>
          {stars ? `${stars} · ` : ''}
          {priceLabel
            ? `${shared.from} ${priceLabel} ${shared.perNight}`
            : shared.priceOnRequest}
        </p>

        {property.amenities.length > 0 ? (
          <p style={{ fontSize: '0.9rem' }}>{property.amenities.join(' · ')}</p>
        ) : null}

        {property.roomTypes.length > 0 ? (
          <ul
            style={{
              listStyle: 'none',
              margin: '1.5rem 0 0',
              padding: 0,
              display: 'grid',
              gap: '0.75rem',
              gridTemplateColumns: 'repeat(auto-fill, minmax(15rem, 1fr))',
            }}
          >
            {property.roomTypes.map((roomType) => (
              <li
                key={roomType.code}
                style={{
                  border: `1px solid ${identity.palette.muted}33`,
                  borderRadius: '0.75rem',
                  padding: '0.9rem',
                  background: '#FFFFFF',
                }}
              >
                <strong style={{ display: 'block' }}>{roomType.name}</strong>
                <span style={{ color: identity.palette.muted, fontSize: '0.875rem' }}>
                  {formatMoney(roomType.basePriceMinor, roomType.currency) ??
                    shared.priceOnRequest}
                </span>
              </li>
            ))}
          </ul>
        ) : null}
      </main>
    </SurfaceShell>
  );
}
