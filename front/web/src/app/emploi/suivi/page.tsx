import type { Metadata } from 'next';

import { normalizeLocale } from '@/lib/i18n';
import { CAREERS_SURFACE, TrackApplicationsForm,
  surfaceUrl,
} from '@/modules/public-surfaces';
import { SurfaceCard, SurfaceMeta, SurfaceShell } from '@/modules/public-surfaces';

/**
 * Suivi de candidature (`/emploi/suivi`) — #9629.
 *
 * ## Pas de compte candidat
 *
 * Le contrat API n'en a pas : le candidat retrouve ses dossiers avec l'adresse
 * email utilisée pour postuler. Cette page le dit, pour qu'il ne cherche pas un
 * « mot de passe oublié » qui n'existe pas.
 *
 * ## Pourquoi le slug d'entreprise est demandé
 *
 * `POST /public/careers/{companySlug}/applications/track` est scopé par
 * entreprise : c'est le contrat existant, et le suivi cross-tenant n'est pas
 * exposé par l'API. On ne prétend donc pas chercher partout — on demande
 * l'entreprise, comme le backend l'exige.
 *
 * `noindex` : page personnalisée, sans valeur d'indexation.
 */

export const metadata: Metadata = {
  title: `${CAREERS_SURFACE.displayName}`,
  description: CAREERS_SURFACE.tagline,
  alternates: { canonical: surfaceUrl(CAREERS_SURFACE, '/emploi/suivi') },
  robots: { index: false, follow: false },
};

interface TrackingPageProps {
  searchParams: Promise<{ company?: string; lang?: string }>;
}

export default async function CareersTrackingPage({ searchParams }: TrackingPageProps) {
  const params = await searchParams;
  const locale = normalizeLocale(params.lang ?? '');
  const companySlug = (params.company ?? '').slice(0, 120);

  return (
    <SurfaceShell identity={CAREERS_SURFACE}>
      <main style={{ maxWidth: '44rem', margin: '0 auto', padding: '2.5rem 1.25rem 4rem' }}>
        <h1 style={{ margin: '0 0 0.35rem', fontSize: 'clamp(1.3rem, 3vw, 1.7rem)' }}>
          {CAREERS_SURFACE.displayName}
        </h1>
        <SurfaceMeta identity={CAREERS_SURFACE}>{CAREERS_SURFACE.tagline}</SurfaceMeta>

        <div style={{ marginTop: '1.5rem' }}>
          <SurfaceCard identity={CAREERS_SURFACE}>
            {companySlug === '' ? (
              <p style={{ margin: 0, fontSize: '0.9rem', color: CAREERS_SURFACE.palette.muted }}>
                {CAREERS_SURFACE.tagline}
              </p>
            ) : (
              <TrackApplicationsForm
                identity={CAREERS_SURFACE}
                companySlug={companySlug}
                locale={locale}
              />
            )}
          </SurfaceCard>
        </div>
      </main>
    </SurfaceShell>
  );
}
