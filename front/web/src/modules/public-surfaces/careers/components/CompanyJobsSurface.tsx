import Link from 'next/link';

import {
  SurfaceBadge,
  SurfaceCard,
  SurfaceCta,
  SurfaceMeta,
  SurfaceNotice,
  SurfaceSectionTitle,
  SurfaceShell,
  type SurfaceIdentity,
} from '../../kernel';
import { getSurfaceCopy } from '../../copy/surfaces-copy';
import { splitOffers, type JobsResult } from '../application/load-jobs';

/**
 * Liste des offres d'une entreprise (#9629).
 *
 * Repris de `front/web/src/app/[companySlug]/careers/page.tsx`, mais rendu par
 * la verticale au lieu d'être réimplémenté dans la page.
 *
 * ## Les deux populations restent séparées
 *
 * Les offres du tenant et les offres agrégées n'ont pas la même nature : les
 * premières se candidatent ici, les secondes renvoient chez le partenaire. Les
 * mélanger ferait cliquer le visiteur sur un formulaire qui n'existe pas pour
 * lui.
 *
 * Composant pur : aucun fetch, aucun état.
 */
export function CompanyJobsSurface({
  identity,
  result,
  companySlug,
  locale,
}: {
  identity: SurfaceIdentity;
  result: JobsResult;
  companySlug: string;
  locale?: string;
}) {
  const copy = getSurfaceCopy(locale);
  const shared = copy.shared;
  const { company, external } = splitOffers(result.cards);

  return (
    <SurfaceShell identity={identity}>
      <main style={{ maxWidth: '56rem', margin: '0 auto', padding: '2.5rem 1.25rem 4rem' }}>
        <Link
          href="/emploi"
          style={{
            display: 'inline-block',
            marginBottom: '1.25rem',
            fontSize: '0.85rem',
            color: identity.palette.muted,
            textDecoration: 'none',
          }}
        >
          ← {shared.allCompanies}
        </Link>

        <h1 style={{ margin: '0 0 0.35rem', fontSize: 'clamp(1.4rem, 3vw, 1.9rem)' }}>
          {result.companyName}
        </h1>
        <SurfaceMeta identity={identity}>
          {result.cards.length} {shared.product}
        </SurfaceMeta>

        {result.failed ? (
          <div style={{ marginTop: '1.25rem' }}>
            <SurfaceNotice identity={identity} alert>
              {shared.unavailable}
            </SurfaceNotice>
          </div>
        ) : null}

        {!result.failed && result.cards.length === 0 ? (
          <div style={{ marginTop: '1.25rem' }}>
            <SurfaceNotice identity={identity}>{shared.empty}</SurfaceNotice>
          </div>
        ) : null}

        {company.length > 0 ? (
          <section style={{ marginTop: '1.75rem' }}>
            {external.length > 0 ? (
              <SurfaceSectionTitle identity={identity}>{shared.company}</SurfaceSectionTitle>
            ) : null}

            <ul style={{ listStyle: 'none', margin: 0, padding: 0, display: 'grid', gap: '0.75rem' }}>
              {company.map(({ offer, contractLabel, remoteLabel, salary, open }) => (
                <SurfaceCard key={offer.id} as="li" interactive identity={identity}>
                  <Link href={`/emploi/${companySlug}/offres/${offer.id}`} data-surface-card-link="">
                    <div
                      style={{
                        display: 'flex',
                        flexWrap: 'wrap',
                        gap: '0.75rem',
                        alignItems: 'baseline',
                        justifyContent: 'space-between',
                      }}
                    >
                      <div style={{ minWidth: '12rem' }}>
                        <strong style={{ display: 'block', fontSize: '1rem' }}>{offer.title}</strong>
                        <SurfaceMeta identity={identity}>
                          {[offer.location, contractLabel, remoteLabel]
                            .filter(Boolean)
                            .join(' · ') || shared.company}
                        </SurfaceMeta>
                      </div>

                      <div style={{ display: 'flex', alignItems: 'center', gap: '0.6rem' }}>
                        {salary ? <strong style={{ fontSize: '0.95rem' }}>{salary}</strong> : null}
                        <SurfaceBadge identity={identity} tone={open ? 'accent' : 'neutral'}>
                          {open ? shared.bookable : shared.soldOut}
                        </SurfaceBadge>
                      </div>
                    </div>
                  </Link>
                </SurfaceCard>
              ))}
            </ul>
          </section>
        ) : null}

        {/* Offres agrégées : lien SORTANT explicite. `rel="noopener noreferrer
            nofollow"` — on ne transmet pas notre referer au partenaire et on ne
            lui cède pas de jus SEO. */}
        {external.length > 0 ? (
          <section style={{ marginTop: '1.75rem' }}>
            <SurfaceSectionTitle identity={identity}>{shared.allCompanies}</SurfaceSectionTitle>

            <ul style={{ listStyle: 'none', margin: 0, padding: 0, display: 'grid', gap: '0.75rem' }}>
              {external.map(({ offer, contractLabel, salary }) => (
                <SurfaceCard key={`ext-${offer.id}`} as="li" identity={identity}>
                  <a
                    href={offer.applyUrl ?? '#'}
                    target="_blank"
                    rel="noopener noreferrer nofollow"
                    style={{ textDecoration: 'none', color: 'inherit' }}
                  >
                    <div
                      style={{
                        display: 'flex',
                        flexWrap: 'wrap',
                        gap: '0.75rem',
                        alignItems: 'baseline',
                        justifyContent: 'space-between',
                      }}
                    >
                      <div style={{ minWidth: '12rem' }}>
                        <strong style={{ display: 'block', fontSize: '1rem' }}>{offer.title}</strong>
                        <SurfaceMeta identity={identity}>
                          {[offer.companyName, offer.location, contractLabel]
                            .filter(Boolean)
                            .join(' · ')}
                        </SurfaceMeta>
                      </div>

                      {salary ? <strong style={{ fontSize: '0.95rem' }}>{salary}</strong> : null}
                    </div>
                  </a>
                </SurfaceCard>
              ))}
            </ul>
          </section>
        ) : null}

        <div style={{ marginTop: '1.75rem' }}>
          <SurfaceCta identity={identity} href={`/emploi/${companySlug}/suivi`}>
            {shared.bookable}
          </SurfaceCta>
        </div>
      </main>
    </SurfaceShell>
  );
}
