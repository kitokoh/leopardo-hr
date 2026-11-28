import type { Metadata } from 'next';
import { normalizeLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';
import { searchPublicRestaurants } from '@/lib/restaurants-public-api';
import {
  RESTAURANT_SURFACE,
  RestaurantExplorer,
  SurfaceCta,
  SurfaceHero,
  SurfaceShell,
  loadRestaurantSurface,
  surfaceUrl,
} from '@/modules/public-surfaces';
import { getSurfaceCopy } from '@/modules/public-surfaces/copy/surfaces-copy';

/**
 * RESTO-903 (#7748) — annuaire public des restaurants (`/restaurants`).
 *
 * Page SSR INDEXABLE (contrairement à /shop et /order, à jeton) : le premier
 * rendu — recherche q, ville, type, cuisine, pagination — est servi côté
 * serveur via `GET /api/v1/public/restaurants` (RESTO-901 #7746, DTO public
 * strict, sans auth). La géolocalisation (« Autour de moi ») raffine ensuite
 * côté client uniquement (la position n'est jamais dans l'URL SSR).
 *
 * #9577 / #9584 — la page est la **vitrine publique de la verticale
 * Restaurant** : l'identité (nom, accroche, palette, domaine canonique) vient
 * de `modules/public-surfaces/restaurants`, et le chargement passe par le cas
 * d'usage `loadRestaurantSurface` (domaine pur + adaptateur).
 *
 * ## #9696 — alignement sur `SurfaceShell`
 *
 * Cette page était la SEULE des huit verticales à ne pas monter le noyau de
 * rendu : elle posait `data-surface` et une couleur de fond à la main, avec un
 * `<header>` et un `<h1>` ad hoc. Résultat : sept vitrines partageaient une
 * composition (palette en variables CSS, hero, CTA, invitation tenant) et la
 * huitième en avait une copie approximative — même contenu, mais pas les mêmes
 * variables CSS, donc pas les mêmes composants enfants, et un titre qui ne
 * venait pas de l'identité.
 *
 * Elle monte désormais `SurfaceShell` + `SurfaceHero` comme les sept autres.
 * L'explorateur client est conservé tel quel : aucune régression de parcours
 * (recherche, filtres, « Autour de moi », pagination SSR crawlable).
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
    alternates: { canonical: surfaceUrl(RESTAURANT_SURFACE) },
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

  const copy = getSurfaceCopy(locale);

  return (
    <SurfaceShell identity={RESTAURANT_SURFACE}>
      <SurfaceHero
        identity={RESTAURANT_SURFACE}
        eyebrow={copy.verticals.restaurant.eyebrow}
        action={
          <SurfaceCta identity={RESTAURANT_SURFACE} href="/decouvrir">
            {copy.shared.allAddresses}
          </SurfaceCta>
        }
      />

      <main style={{ maxWidth: '72rem', margin: '0 auto', padding: '2rem 1.25rem 4rem' }}>
        <RestaurantExplorer
          locale={locale}
          initialItems={result?.items ?? []}
          initialMeta={result?.meta ?? null}
          initialError={result === null}
          initialQuery={query}
        />
      </main>
    </SurfaceShell>
  );
}
