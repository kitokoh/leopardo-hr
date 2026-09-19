import type { Metadata } from 'next';
import { normalizeLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';
import { SITE_URL } from '@/lib/site-url';
import { searchPublicRestaurants } from '@/lib/restaurants-public-api';
import RestaurantsExplorer from './restaurants-explorer';

/**
 * RESTO-903 (#7748) — annuaire public des restaurants (`/restaurants`).
 *
 * Page SSR INDEXABLE (contrairement à /shop et /order, à jeton) : le premier
 * rendu — recherche q, ville, type, cuisine, pagination — est servi côté
 * serveur via `GET /api/v1/public/restaurants` (RESTO-901 #7746, DTO public
 * strict, sans auth). La géolocalisation (« Autour de moi ») raffine ensuite
 * côté client uniquement (la position n'est jamais dans l'URL SSR).
 */

interface RestaurantsPageProps {
  searchParams: Promise<{
    q?: string;
    city?: string;
    type?: string;
    cuisine?: string;
    page?: string;
    lang?: string;
  }>;
}

export async function generateMetadata({ searchParams }: RestaurantsPageProps): Promise<Metadata> {
  const { lang } = await searchParams;
  const locale = normalizeLocale(lang ?? '');

  return {
    title: t(locale, 'restaurant.public.directoryTitle'),
    description: t(locale, 'restaurant.public.directoryDescription'),
    alternates: { canonical: `${SITE_URL}/restaurants` },
    robots: { index: true, follow: true },
    openGraph: {
      title: t(locale, 'restaurant.public.directoryTitle'),
      description: t(locale, 'restaurant.public.directoryDescription'),
      type: 'website',
    },
  };
}

export default async function RestaurantsPage({ searchParams }: RestaurantsPageProps) {
  const params = await searchParams;
  const locale = normalizeLocale(params.lang ?? '');

  const page = Math.max(1, Number.parseInt(params.page ?? '1', 10) || 1);
  const query = {
    q: (params.q ?? '').slice(0, 120),
    city: (params.city ?? '').slice(0, 120),
    type: params.type ?? '',
    cuisine: (params.cuisine ?? '').slice(0, 40),
    page,
  };

  const result = await searchPublicRestaurants({
    q: query.q || undefined,
    city: query.city || undefined,
    type: query.type || undefined,
    cuisine: query.cuisine || undefined,
    page: query.page,
  });

  return (
    <main className="min-h-screen bg-gradient-to-br from-slate-50 via-amber-50/40 to-orange-50/40 px-4 py-10">
      <div className="mx-auto max-w-5xl">
        <header className="mb-8">
          <h1 className="text-3xl font-black tracking-tight text-slate-950">
            {t(locale, 'restaurant.public.directoryTitle')}
          </h1>
          <p className="mt-1 text-sm text-slate-500">
            {t(locale, 'restaurant.public.directoryDescription')}
          </p>
        </header>

        <RestaurantsExplorer
          locale={locale}
          initialItems={result?.items ?? []}
          initialMeta={result?.meta ?? null}
          initialError={result === null}
          initialQuery={query}
        />
      </div>
    </main>
  );
}
