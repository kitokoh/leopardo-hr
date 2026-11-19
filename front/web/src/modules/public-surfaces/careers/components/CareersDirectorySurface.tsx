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
  SurfaceTenantInvitation,
  type SurfaceIdentity,
} from '../../kernel';
import { getSurfaceCopy } from '../../copy/surfaces-copy';
import type { HiringDirectoryResult } from '../application/load-jobs';

/**
 * Annuaire des entreprises qui recrutent (#9629).
 *
 * ## Ce que cette page change
 *
 * Avant, un candidat devait **connaître le slug** d'une entreprise pour voir
 * ses offres : `GET /public/careers` existait mais n'alimentait que le sitemap.
 * Cette page est le point d'entrée qui manquait — on part de la liste des
 * entreprises, pas d'une URL devinée.
 *
 * Composition propre : grille large (on lit des noms d'entreprises, pas des
 * prix), la date de dernière publication est le signal de fraîcheur.
 *
 * Composant pur : aucun fetch, aucun état.
 */
export function CareersDirectorySurface({
  identity,
  result,
  locale,
}: {
  identity: SurfaceIdentity;
  result: HiringDirectoryResult;
  locale?: string;
}) {
  const copy = getSurfaceCopy(locale);
  const shared = copy.shared;

  return (
    <SurfaceShell identity={identity}>
      <SurfaceHero
        identity={identity}
        eyebrow={copy.verticals.careers.eyebrow}
        action={
          <SurfaceCta identity={identity} href="/emploi/suivi">
            {shared.bookable}
          </SurfaceCta>
        }
      />

      <main style={{ maxWidth: '72rem', margin: '0 auto', padding: '2rem 1.25rem 4rem' }}>
        {result.failed ? (
          <SurfaceNotice identity={identity} alert>
            {shared.unavailable}
          </SurfaceNotice>
        ) : null}

        {!result.failed && result.companies.length === 0 ? (
          <SurfaceNotice identity={identity}>{shared.comingSoon}</SurfaceNotice>
        ) : null}

        {!result.failed && result.companies.length > 0 ? (
          <SurfaceGrid min="18rem">
            {result.companies.map((company) => (
              <SurfaceCard key={company.slug} as="li" interactive identity={identity}>
                <Link href={`/emploi/${company.slug}`} data-surface-card-link="">
                  <strong style={{ fontSize: '1.02rem', lineHeight: 1.3 }}>{company.name}</strong>

                  <SurfaceMeta identity={identity}>
                    {company.country ?? shared.company}
                  </SurfaceMeta>

                  {company.lastPublishedAt ? (
                    <div>
                      <SurfaceBadge identity={identity} tone="accent">
                        {company.lastPublishedAt.slice(0, 10)}
                      </SurfaceBadge>
                    </div>
                  ) : null}
                </Link>
              </SurfaceCard>
            ))}
          </SurfaceGrid>
        ) : null}

        <SurfaceTenantInvitation identity={identity} locale={locale} />
      </main>
    </SurfaceShell>
  );
}
