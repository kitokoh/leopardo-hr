import type { Metadata } from 'next';
import { normalizeLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';
import { SITE_URL } from '@/lib/site-url';
import { searchPublicRestaurants } from '@/lib/restaurants-public-api';
import {
  RESTAURANT_SURFACE,
  RestaurantExplorer,
  loadRestaurantSurface,
} from '@/modules/public-surfaces';

/**
 * RESTO-903 (#7748) — annuaire public des restaurants (`/restaurants`).
 *
 * Page SSR INDEXABLE (contrairement à /shop et /order, à jeton) : le premier
 * rendu — recherche q, ville, type, cuisine, pagination — est servi côté
 * serveur via `GET /api/v1/public/restaurants` (RESTO-901 #7746, DTO public
 * strict, sans auth). La géolocalisation (« Autour de moi ») raffine ensuite
 * côté client uniquement (la position n'est jamais dans l'URL SSR).
 *
 * #9577 / #9584 — la page est désormais la **vitrine publique de la verticale
 * Restaurant** : l'identité (nom, accroche, palette, domaine canonique) vient
 * de `modules/public-surfaces/restaurants`, et le chargement passe par le cas
 * d'usage `loadRestaurantSurface` (domaine pur + adaptateur). L'explorateur
 * client existant est conservé tel quel : aucune régression de parcours.
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
    alternates: { canonical: `${SITE_URL}${RESTAURANT_SURFACE.basePath}` },
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

  // #9577 — chargement via le cas d'usage de la vitrine (domaine + adaptateur).
  // `failed` distingue une panne API d'un annuaire réellement vide.
  const surface = await loadRestaurantSurface({
    q: query.q || undefined,
    city: query.city || undefined,
    kind: query.type || undefined,
    cuisine: query.cuisine || undefined,
    page: query.page,
  });

  // Le premier rendu SSR reste servi par l'explorateur existant (HTML
  // indexable, filtres, pagination) : la vitrine apporte l'identité et le
  // chargement, pas un second parcours concurrent.
  const result = surface.failed
    ? null
    : await searchPublicRestaurants({
        q: query.q || undefined,
        city: query.city || undefined,
        type: query.type || undefined,
        cuisine: query.cuisine || undefined,
        page: query.page,
      });

  return (
    <div
      data-surface={RESTAURANT_SURFACE.vertical}
      className="min-h-screen px-4 py-10"
      style={{ backgroundColor: RESTAURANT_SURFACE.palette.surface }}
    >
      <div className="mx-auto max-w-5xl">
        <header className="mb-8">
          <p
            className="text-xs uppercase tracking-[0.14em]"
            style={{ color: RESTAURANT_SURFACE.palette.muted }}
          >
            {RESTAURANT_SURFACE.displayName}
          </p>
          <h1 className="mt-1 text-3xl font-black tracking-tight text-slate-950">
            {t(locale, 'restaurant.public.directoryTitle')}
          </h1>
          <p className="mt-1 text-sm text-slate-500">
            {t(locale, 'restaurant.public.directoryDescription')}
          </p>
        </header>

        <RestaurantExplorer
          locale={locale}
          initialItems={result?.items ?? []}
          initialMeta={result?.meta ?? null}
          initialError={result === null}
          initialQuery={query}
        />
      </div>
    </div>
  );
}
