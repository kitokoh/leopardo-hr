import type { ReactNode } from 'react';

import type { SurfaceIdentity } from '../domain/surface-identity';

/**
 * Bandeau d'accroche d'une vitrine verticale.
 *
 * Purement présentationnel : il reçoit l'identité et un éventuel CTA. Le nom et
 * l'accroche viennent de l'identité de la verticale, jamais d'une constante
 * partagée — deux vitrines ne peuvent pas afficher le même titre par accident.
 */
export function SurfaceHero({
  identity,
  eyebrow,
  action,
}: {
  identity: SurfaceIdentity;
  eyebrow?: string;
  action?: ReactNode;
}) {
  return (
    <header
      style={{
        padding: 'clamp(2rem, 6vw, 4.5rem) 1.25rem',
        borderBottom: `1px solid ${identity.palette.muted}33`,
      }}
    >
      <div style={{ maxWidth: '72rem', margin: '0 auto' }}>
        {eyebrow ? (
          <p
            style={{
              margin: 0,
              fontSize: '0.75rem',
              letterSpacing: '0.14em',
              textTransform: 'uppercase',
              color: identity.palette.muted,
            }}
          >
            {eyebrow}
          </p>
        ) : null}

        <h1
          style={{
            margin: '0.5rem 0 0',
            fontSize: 'clamp(1.9rem, 5vw, 3.1rem)',
            lineHeight: 1.1,
            letterSpacing: '-0.02em',
          }}
        >
          {identity.displayName}
        </h1>

        <p
          style={{
            margin: '0.9rem 0 0',
            maxWidth: '46rem',
            fontSize: 'clamp(1rem, 2vw, 1.15rem)',
            color: identity.palette.muted,
          }}
        >
          {identity.tagline}
        </p>

        {action ? <div style={{ marginTop: '1.6rem' }}>{action}</div> : null}
      </div>
    </header>
  );
}
