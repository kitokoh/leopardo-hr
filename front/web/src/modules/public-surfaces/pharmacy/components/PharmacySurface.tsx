import Link from 'next/link';

import { SurfaceHero, SurfaceShell, type SurfaceIdentity } from '../../kernel';
import { getSurfaceCopy } from '../../copy/surfaces-copy';
import type { PharmacySurfaceResult } from '../application/load-pharmacy-surface';

/**
 * Rendu de la vitrine Pharmacie.
 *
 * Composant pur. Les trois états sont rendus explicitement : une verticale pas
 * encore alimentée ne doit jamais ressembler à une recherche sans résultat.
 * Toute chaîne visible vient du catalogue de copie.
 */
export function PharmacySurface({
  identity,
  result,
  locale,
}: {
  identity: SurfaceIdentity;
  result: PharmacySurfaceResult;
  locale?: string;
}) {
  const copy = getSurfaceCopy(locale);
  const shared = copy.shared;

  return (
    <SurfaceShell identity={identity}>
      <SurfaceHero
        identity={identity}
        eyebrow={copy.verticals.pharmacy.eyebrow}
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
        {result.state === 'unavailable' ? (
          <p role="alert" style={{ color: identity.palette.muted }}>
            {shared.unavailable}
          </p>
        ) : null}

        {result.state === 'empty' ? (
          <p style={{ color: identity.palette.muted }}>{shared.comingSoon}</p>
        ) : null}

        {result.state === 'ready' ? (
          <ul
            style={{
              listStyle: 'none',
              margin: 0,
              padding: 0,
              display: 'grid',
              gap: '0.75rem',
              gridTemplateColumns: 'repeat(auto-fill, minmax(16rem, 1fr))',
            }}
          >
            {result.cards.map(({ pharmacy, duty }) => (
              <li
                key={pharmacy.slug}
                style={{
                  border: `1px solid ${identity.palette.muted}33`,
                  borderRadius: '0.75rem',
                  padding: '1rem',
                  background: '#FFFFFF',
                }}
              >
                <strong style={{ display: 'block' }}>{pharmacy.name}</strong>
                <span style={{ color: identity.palette.muted, fontSize: '0.875rem' }}>
                  {[pharmacy.city, duty].filter(Boolean).join(' · ') || shared.pharmacy}
                </span>
              </li>
            ))}
          </ul>
        ) : null}
      </main>
    </SurfaceShell>
  );
}
