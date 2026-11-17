import Link from 'next/link';

import { SurfaceHero, SurfaceShell, type SurfaceIdentity } from '../../kernel';
import type { CompanyView } from '../application/load-company-view';

/**
 * Rendu de la vitrine B2B.
 *
 * Composant pur : reçoit une entreprise déjà chargée. Aucun fetch, aucun état.
 */
export function CompanySurface({
  identity,
  view,
}: {
  identity: SurfaceIdentity;
  view: CompanyView;
}) {
  const { company, location, directions } = view;

  return (
    <SurfaceShell identity={identity}>
      <SurfaceHero
        identity={identity}
        eyebrow={location ?? 'Entreprise'}
        action={
          <Link
            href="/entreprises"
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
            Toutes les entreprises
          </Link>
        }
      />

      <main style={{ maxWidth: '72rem', margin: '0 auto', padding: '2rem 1.25rem 4rem' }}>
        <h2 style={{ marginTop: 0, fontSize: '1.4rem' }}>{company.name}</h2>

        {directions.length > 0 ? (
          <p style={{ color: identity.palette.muted }}>{directions.join(' · ')}</p>
        ) : null}

        {company.description ? <p>{company.description}</p> : null}

        {company.productCategories.length > 0 ? (
          <ul
            style={{
              listStyle: 'none',
              margin: '1.5rem 0 0',
              padding: 0,
              display: 'flex',
              flexWrap: 'wrap',
              gap: '0.5rem',
            }}
          >
            {company.productCategories.map((category) => (
              <li
                key={category}
                style={{
                  border: `1px solid ${identity.palette.muted}33`,
                  borderRadius: '999px',
                  padding: '0.35rem 0.8rem',
                  fontSize: '0.875rem',
                  background: '#FFFFFF',
                }}
              >
                {category}
              </li>
            ))}
          </ul>
        ) : null}
      </main>
    </SurfaceShell>
  );
}
