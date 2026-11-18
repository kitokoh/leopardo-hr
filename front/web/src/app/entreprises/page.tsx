import type { Metadata } from 'next';

import { normalizeLocale } from '@/lib/i18n';
import { SITE_URL } from '@/lib/site-url';
import {
  CompanyDirectorySurface,
  TRADE_SURFACE,
  loadCompanyDirectoryView,
} from '@/modules/public-surfaces';

/**
 * Vitrine publique B2B — annuaire (`/entreprises`), #9618.
 *
 * ## Ce qui a changé
 *
 * Cette page était un appel de 19 lignes à `buildDirectoryPage({ type, basePath })`
 * — la même fabrique que `/boutiques` et `/stay`. `CompanyDirectorySurface`
 * existait sans être monté.
 *
 * Elle rend désormais sa propre composition (une colonne dense, catégories en
 * badges, aucun badge de direction inventé) et charge via son cas d'usage.
 *
 * SSR indexable, alimentée par `GET /api/v1/public/discover?type=trade_company`
 * (opt-in `trade_company_profiles.is_public`, exposé par #9617).
 */

interface EntreprisesPageProps {
  searchParams: Promise<{ q?: string; city?: string; page?: string; lang?: string }>;
}

export async function generateMetadata({ searchParams }: EntreprisesPageProps): Promise<Metadata> {
  const { lang } = await searchParams;
  const locale = normalizeLocale(lang ?? '');

  return {
    title: TRADE_SURFACE.displayName,
    description: TRADE_SURFACE.tagline,
    alternates: { canonical: `${SITE_URL}${TRADE_SURFACE.basePath}` },
    robots: { index: true, follow: true },
    openGraph: {
      title: TRADE_SURFACE.displayName,
      description: TRADE_SURFACE.tagline,
      type: 'website',
      locale,
    },
  };
}

export default async function EntreprisesPage({ searchParams }: EntreprisesPageProps) {
  const params = await searchParams;
  const locale = normalizeLocale(params.lang ?? '');

  const result = await loadCompanyDirectoryView({
    q: (params.q ?? '').slice(0, 120) || undefined,
    city: (params.city ?? '').slice(0, 120) || undefined,
    page: Math.max(1, Number.parseInt(params.page ?? '1', 10) || 1),
    locale,
  });

  return <CompanyDirectorySurface identity={TRADE_SURFACE} result={result} locale={locale} />;
}
