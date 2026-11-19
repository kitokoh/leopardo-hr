import type { Metadata } from 'next';

import { normalizeLocale } from '@/lib/i18n';
import { SITE_URL } from '@/lib/site-url';
import { RETAIL_SURFACE, CatalogSurface, loadCatalog } from '@/modules/public-surfaces';

/**
 * Catalogue public de la vitrine Retail (`/produits`) — #9627.
 *
 * Repris de `front/marketplace/src/app/produits/page.tsx` lors de l'absorption
 * de l'app autonome. La page ne fait plus que : lire les `searchParams`,
 * appeler le cas d'usage, monter la composition. Toute la logique (mapping,
 * formatage, distinction panne/vide) vit dans la verticale.
 *
 * SSR indexable, alimentée par `GET /public/market/products`.
 */

interface CatalogPageProps {
  searchParams: Promise<{
    q?: string;
    seller?: string;
    category?: string;
    page?: string;
    lang?: string;
  }>;
}

export async function generateMetadata({ searchParams }: CatalogPageProps): Promise<Metadata> {
  const { lang } = await searchParams;
  const locale = normalizeLocale(lang ?? '');

  return {
    title: RETAIL_SURFACE.displayName,
    description: RETAIL_SURFACE.tagline,
    alternates: { canonical: `${SITE_URL}/produits` },
    robots: { index: true, follow: true },
    openGraph: {
      title: RETAIL_SURFACE.displayName,
      description: RETAIL_SURFACE.tagline,
      type: 'website',
      locale,
    },
  };
}

export default async function CatalogPage({ searchParams }: CatalogPageProps) {
  const params = await searchParams;
  const locale = normalizeLocale(params.lang ?? '');

  const result = await loadCatalog({
    q: (params.q ?? '').slice(0, 120) || undefined,
    seller: (params.seller ?? '').slice(0, 120) || undefined,
    category: (params.category ?? '').slice(0, 120) || undefined,
    page: Math.max(1, Number.parseInt(params.page ?? '1', 10) || 1),
    locale,
  });

  return <CatalogSurface identity={RETAIL_SURFACE} result={result} locale={locale} />;
}
