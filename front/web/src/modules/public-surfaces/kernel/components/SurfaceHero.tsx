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
        position: 'relative',
        overflow: 'hidden',
        padding: 'clamp(2.5rem, 6vw, 4.75rem) 1.25rem',
        borderBottom: `1px solid ${identity.palette.muted}25`,
        background: `radial-gradient(75% 75% at 50% 0%, ${identity.palette.primary}12 0%, ${identity.palette.surface} 100%)`,
      }}
    >
      <div style={{ maxWidth: '72rem', margin: '0 auto', position: 'relative' }}>
        {eyebrow ? (
          <div
            style={{
              display: 'inline-flex',
              alignItems: 'center',
              gap: '0.45rem',
              padding: '0.3rem 0.8rem',
              borderRadius: '9999px',
              border: `1px solid ${identity.palette.muted}33`,
              background: '#FFFFFF',
              fontSize: '0.75rem',
              fontWeight: 700,
              letterSpacing: '0.12em',
              textTransform: 'uppercase',
              color: identity.palette.onSurface,
              boxShadow: '0 1px 2px rgba(15, 23, 42, 0.04)',
            }}
          >
            <span
              style={{
                width: '0.45rem',
                height: '0.45rem',
                borderRadius: '9999px',
                background: identity.palette.accent,
                display: 'inline-block',
              }}
              aria-hidden="true"
            />
            <span>{eyebrow}</span>
          </div>
        ) : null}

        <h1
          style={{
            margin: '0.75rem 0 0',
            fontSize: 'clamp(2rem, 5.2vw, 3.25rem)',
            fontWeight: 900,
            lineHeight: 1.1,
            letterSpacing: '-0.025em',
            color: identity.palette.onSurface,
          }}
        >
          {identity.displayName}
        </h1>

        <p
          style={{
            margin: '0.85rem 0 0',
            maxWidth: '46rem',
            fontSize: 'clamp(1rem, 2vw, 1.15rem)',
            lineHeight: 1.55,
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
