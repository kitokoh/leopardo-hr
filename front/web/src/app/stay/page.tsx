import type { Metadata } from 'next';

import { normalizeLocale } from '@/lib/i18n';
import {
  HOSPITALITY_SURFACE,
  PropertyDirectorySurface,
  loadPropertyDirectoryView,
  surfaceUrl,
} from '@/modules/public-surfaces';

/**
 * Vitrine publique Hôtellerie — annuaire (`/stay`), #9618.
 *
 * ## Ce qui a changé
 *
 * Cette page était un appel de 19 lignes à `buildDirectoryPage({ type, basePath })`
 * — la même fabrique que `/boutiques` et `/entreprises`. `PropertyDirectorySurface`
 * existait sans être monté.
 *
 * Elle rend désormais sa propre composition (grille large, type d'hébergement en
 * tête, aucun prix inventé) et charge via son cas d'usage.
 *
 * SSR indexable, alimentée par `GET /api/v1/public/discover?type=hospitality_property`.
 */

interface StayPageProps {
  searchParams: Promise<{ q?: string; city?: string; page?: string; lang?: string }>;
}

export async function generateMetadata({ searchParams }: StayPageProps): Promise<Metadata> {
  const { lang } = await searchParams;
  const locale = normalizeLocale(lang ?? '');

  return {
    title: HOSPITALITY_SURFACE.displayName,
    description: HOSPITALITY_SURFACE.tagline,
    alternates: { canonical: surfaceUrl(HOSPITALITY_SURFACE) },
    robots: { index: true, follow: true },
    openGraph: {
      title: HOSPITALITY_SURFACE.displayName,
      description: HOSPITALITY_SURFACE.tagline,
      type: 'website',
      locale,
    },
  };
}

export default async function StayPage({ searchParams }: StayPageProps) {
  const params = await searchParams;
  const locale = normalizeLocale(params.lang ?? '');

  const result = await loadPropertyDirectoryView({
    q: (params.q ?? '').slice(0, 120) || undefined,
    city: (params.city ?? '').slice(0, 120) || undefined,
    page: Math.max(1, Number.parseInt(params.page ?? '1', 10) || 1),
    locale,
  });

  return (
    <PropertyDirectorySurface
      identity={HOSPITALITY_SURFACE}
      result={result}
      locale={locale}
      searchParams={{ q: params.q, city: params.city }}
    />
  );
}
