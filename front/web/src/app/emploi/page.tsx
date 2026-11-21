import type { Metadata } from 'next';

import { normalizeLocale } from '@/lib/i18n';
import { SITE_URL } from '@/lib/site-url';
import {
  CAREERS_SURFACE,
  CareersDirectorySurface,
  loadHiringDirectory,
} from '@/modules/public-surfaces';

/**
 * Annuaire public des entreprises qui recrutent (`/emploi`) — #9629.
 *
 * ## Le trou que cette page comble
 *
 * `GET /public/careers` existait déjà mais n'alimentait que le sitemap : un
 * candidat devait **connaître le slug** d'une entreprise pour voir ses offres.
 * Cette page est le point d'entrée cross-tenant qui manquait.
 *
 * SSR indexable, alimentée par `GET /public/careers`.
 */

export async function generateMetadata(): Promise<Metadata> {
  return {
    title: CAREERS_SURFACE.displayName,
    description: CAREERS_SURFACE.tagline,
    alternates: { canonical: `${SITE_URL}${CAREERS_SURFACE.basePath}` },
    robots: { index: true, follow: true },
    openGraph: {
      title: CAREERS_SURFACE.displayName,
      description: CAREERS_SURFACE.tagline,
      type: 'website',
    },
  };
}

interface CareersPageProps {
  searchParams: Promise<{ q?: string; page?: string; lang?: string }>;
}

export default async function CareersPage({ searchParams }: CareersPageProps) {
  const params = await searchParams;
  const locale = normalizeLocale(params.lang ?? '');

  const result = await loadHiringDirectory({
    q: (params.q ?? '').slice(0, 120) || undefined,
    page: Math.max(1, Number.parseInt(params.page ?? '1', 10) || 1),
  });

  return (
    <CareersDirectorySurface
      identity={CAREERS_SURFACE}
      result={result}
      locale={locale}
      searchParams={{ q: params.q }}
    />
  );
}
