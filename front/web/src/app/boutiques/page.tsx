import type { Metadata } from 'next';

import { normalizeLocale } from '@/lib/i18n';
import { SITE_URL } from '@/lib/site-url';
import {
  RETAIL_SURFACE,
  ShopDirectorySurface,
  loadShopDirectoryView,
} from '@/modules/public-surfaces';

/**
 * Vitrine publique Retail — annuaire (`/boutiques`), #9618.
 *
 * ## Ce qui a changé
 *
 * Cette page était un appel de 19 lignes à `buildDirectoryPage({ type, basePath })`
 * — la **même** fabrique que `/stay` et `/entreprises`, donc le même gabarit
 * visuel. Le composant `ShopDirectorySurface` existait, était testé, portait une
 * composition propre… et n'était monté nulle part.
 *
 * Elle rend désormais sa propre composition (grille dense, catégorie en signal
 * principal) et charge via son cas d'usage, qui distingue panne et annuaire vide.
 *
 * SSR indexable, alimentée par `GET /api/v1/public/discover?type=retail_shop`
 * (opt-in `retail_online_settings.enabled`).
 */

interface BoutiquesPageProps {
  searchParams: Promise<{ q?: string; city?: string; page?: string; lang?: string }>;
}

export async function generateMetadata({ searchParams }: BoutiquesPageProps): Promise<Metadata> {
  const { lang } = await searchParams;
  const locale = normalizeLocale(lang ?? '');

  return {
    title: RETAIL_SURFACE.displayName,
    description: RETAIL_SURFACE.tagline,
    alternates: { canonical: `${SITE_URL}${RETAIL_SURFACE.basePath}` },
    robots: { index: true, follow: true },
    openGraph: {
      title: RETAIL_SURFACE.displayName,
      description: RETAIL_SURFACE.tagline,
      type: 'website',
      locale,
    },
  };
}

export default async function BoutiquesPage({ searchParams }: BoutiquesPageProps) {
  const params = await searchParams;
  const locale = normalizeLocale(params.lang ?? '');

  const result = await loadShopDirectoryView({
    q: (params.q ?? '').slice(0, 120) || undefined,
    city: (params.city ?? '').slice(0, 120) || undefined,
    page: Math.max(1, Number.parseInt(params.page ?? '1', 10) || 1),
  });

  return <ShopDirectorySurface identity={RETAIL_SURFACE} result={result} locale={locale} />;
}
