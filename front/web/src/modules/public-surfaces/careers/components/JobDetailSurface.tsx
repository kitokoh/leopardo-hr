import Link from 'next/link';

import {
  SurfaceBadge,
  SurfaceCard,
  SurfaceMeta,
  SurfaceNotice,
  SurfaceShell,
  type SurfaceIdentity,
} from '../../kernel';
import { getSurfaceCopy } from '../../copy/surfaces-copy';
import type { JobDetailView } from '../application/load-job-detail';
import { ApplyForm } from './ApplyForm';

/**
 * Fiche d'une offre d'emploi (#9629).
 *
 * Repris de `front/web/src/app/[companySlug]/careers/jobs/[jobId]/page.tsx`.
 *
 * ## Une offre agrégée ne montre pas de formulaire
 *
 * Si `offer.external`, la candidature se fait chez le partenaire : on affiche
 * un lien sortant, jamais un formulaire qui échouerait. C'est la règle du
 * domaine (`external`), pas une condition d'affichage.
 *
 * Composant serveur : le formulaire est un îlot client séparé.
 */
export function JobDetailSurface({
  identity,
  view,
  companySlug,
  locale,
}: {
  identity: SurfaceIdentity;
  view: JobDetailView;
  companySlug: string;
  locale?: string;
}) {
  const copy = getSurfaceCopy(locale);
  const shared = copy.shared;
  const { offer, card } = view;

  return (
    <SurfaceShell identity={identity}>
      <main style={{ maxWidth: '52rem', margin: '0 auto', padding: '2.5rem 1.25rem 4rem' }}>
        <Link
          href={`/emploi/${companySlug}`}
          style={{
            display: 'inline-flex',
            alignItems: 'center',
            gap: '0.35rem',
            marginBottom: '1.5rem',
            fontSize: '0.85rem',
            color: identity.palette.muted,
            textDecoration: 'none',
            transition: 'color 0.15s ease',
          }}
        >
          ← {offer.companyName}
        </Link>

        <SurfaceCard identity={identity}>
          <h1
            style={{
              margin: 0,
              fontSize: 'clamp(1.35rem, 3vw, 1.85rem)',
              lineHeight: 1.2,
              letterSpacing: '-0.02em',
              fontWeight: 800,
            }}
          >
            {offer.title}
          </h1>

          <SurfaceMeta identity={identity}>
            {[offer.companyName, offer.location, card.contractLabel, card.remoteLabel]
              .filter(Boolean)
              .join(' · ')}
          </SurfaceMeta>

          <div style={{ display: 'flex', flexWrap: 'wrap', gap: '0.65rem', marginTop: '0.85rem' }}>
            {card.salary ? (
              <SurfaceBadge identity={identity} tone="accent">
                {card.salary}
              </SurfaceBadge>
            ) : null}

            <SurfaceBadge identity={identity} tone={card.open ? 'accent' : 'neutral'}>
              {card.open ? shared.bookable : shared.soldOut}
            </SurfaceBadge>

            {offer.publishedAt ? (
              <SurfaceMeta identity={identity}>{offer.publishedAt.slice(0, 10)}</SurfaceMeta>
            ) : null}
          </div>

          {offer.description ? (
            <div
              style={{
                marginTop: '1.5rem',
                fontSize: '0.95rem',
                lineHeight: 1.7,
                whiteSpace: 'pre-line',
                color: identity.palette.muted,
              }}
            >
              {offer.description}
            </div>
          ) : null}
        </SurfaceCard>

        <div style={{ marginTop: '1.75rem' }}>
          {!card.open ? (
            <SurfaceNotice identity={identity}>{shared.soldOut}</SurfaceNotice>
          ) : offer.external ? (
            <SurfaceCard identity={identity}>
              <p style={{ margin: '0 0 0.85rem', fontSize: '0.9rem', color: identity.palette.muted }}>{shared.company}</p>
              <a
                href={offer.applyUrl ?? '#'}
                target="_blank"
                rel="noopener noreferrer nofollow"
                style={{
                  display: 'inline-flex',
                  alignItems: 'center',
                  gap: '0.35rem',
                  padding: '0.75rem 1.25rem',
                  borderRadius: '0.75rem',
                  background: identity.palette.primary,
                  color: identity.palette.onPrimary,
                  fontWeight: 700,
                  fontSize: '0.9rem',
                  textDecoration: 'none',
                  boxShadow: `0 2px 8px ${identity.palette.primary}30`,
                  transition: 'filter 0.15s ease, box-shadow 0.15s ease',
                }}
              >
                {shared.bookable} ↗
              </a>
            </SurfaceCard>
          ) : (
            <ApplyForm
              identity={identity}
              companySlug={companySlug}
              jobId={offer.id}
              locale={locale}
            />
          )}
        </div>
      </main>
    </SurfaceShell>
  );
}
