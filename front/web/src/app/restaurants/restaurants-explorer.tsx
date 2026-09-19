'use client';

import { useMemo, useState } from 'react';
import Link from 'next/link';
import { AlertTriangle, LocateFixed, MapPin, Search, Star, Store } from 'lucide-react';
import { apiFetch } from '@/lib/api-client';
import type { AppLocale } from '@/lib/i18n';
import { catalogDirection, t } from '@/lib/i18n/locale-catalog';
import {
  ESTABLISHMENT_TYPES,
  buildRestaurantSearchQuery,
  type PublicListMeta,
  type PublicRestaurantSummary,
} from '@/lib/restaurants-public-api';
import { establishmentTypeLabel, formatRating } from './format';

/**
 * RESTO-903 (#7748) — explorateur client de l'annuaire public.
 *
 * Le PREMIER rendu (sans `near`) vient du Server Component `/restaurants`
 * (données SSR passées en props → HTML indexable). Les interactions
 * (recherche, filtres, « Autour de moi », pagination en mode proximité)
 * raffinent ensuite côté client via le proxy same-origin `/api/v1`.
 * Hors proximité, la pagination reste des liens `<a>` SSR (crawlables).
 */

interface RestaurantsExplorerProps {
  locale: AppLocale;
  initialItems: PublicRestaurantSummary[];
  initialMeta: PublicListMeta | null;
  /** true si le fetch SSR initial a échoué (API indisponible). */
  initialError: boolean;
  initialQuery: { q: string; city: string; type: string; cuisine: string; page: number };
}

type GeoState = 'idle' | 'locating' | 'active' | 'denied' | 'unsupported';

function directoryHref(query: {
  q: string;
  city: string;
  type: string;
  cuisine: string;
  page: number;
}): string {
  const qs = buildRestaurantSearchQuery(query);
  return qs ? `/restaurants?${qs}` : '/restaurants';
}

export default function RestaurantsExplorer({
  locale,
  initialItems,
  initialMeta,
  initialError,
  initialQuery,
}: RestaurantsExplorerProps) {
  const dir = catalogDirection(locale);

  const [q, setQ] = useState(initialQuery.q);
  const [city, setCity] = useState(initialQuery.city);
  const [type, setType] = useState(initialQuery.type);
  const [cuisine, setCuisine] = useState(initialQuery.cuisine);
  const [items, setItems] = useState(initialItems);
  const [meta, setMeta] = useState(initialMeta);
  const [page, setPage] = useState(initialQuery.page);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(initialError);
  const [geoState, setGeoState] = useState<GeoState>('idle');
  const [near, setNear] = useState<string | null>(null);

  const fetchResults = async (params: {
    q: string;
    city: string;
    type: string;
    cuisine: string;
    page: number;
    near: string | null;
  }) => {
    setLoading(true);
    setError(false);
    try {
      const qs = buildRestaurantSearchQuery({
        q: params.q,
        city: params.city,
        type: params.type,
        cuisine: params.cuisine,
        page: params.page,
        near: params.near ?? undefined,
      });
      const res = await apiFetch(`/public/restaurants${qs ? `?${qs}` : ''}`, { _cacheBust: true });
      if (!res.ok) {
        throw new Error(String(res.status));
      }
      const json = (await res.json()) as {
        data?: PublicRestaurantSummary[];
        meta?: PublicListMeta;
      };
      setItems(Array.isArray(json.data) ? json.data : []);
      setMeta(json.meta ?? null);
      setPage(params.page);
      if (typeof window !== 'undefined' && !params.near) {
        // URL partageable/crawlable — sans near (position = donnée volatile).
        window.history.replaceState(null, '', directoryHref(params));
      }
    } catch {
      setError(true);
    } finally {
      setLoading(false);
    }
  };

  const submitSearch = (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    void fetchResults({ q, city, type, cuisine, page: 1, near });
  };

  const locateMe = () => {
    if (typeof navigator === 'undefined' || !navigator.geolocation) {
      setGeoState('unsupported');
      return;
    }
    setGeoState('locating');
    navigator.geolocation.getCurrentPosition(
      (position) => {
        const value = `${position.coords.latitude.toFixed(5)},${position.coords.longitude.toFixed(5)}`;
        setNear(value);
        setGeoState('active');
        void fetchResults({ q, city, type, cuisine, page: 1, near: value });
      },
      () => setGeoState('denied'),
      { timeout: 10_000 },
    );
  };

  const clearNear = () => {
    setNear(null);
    setGeoState('idle');
    void fetchResults({ q, city, type, cuisine, page: 1, near: null });
  };

  const lastPage = meta?.last_page ?? 1;

  const distanceText = useMemo(
    () => (value: number) => {
      try {
        return `${new Intl.NumberFormat(locale, { maximumFractionDigits: 1 }).format(value)} km`;
      } catch {
        return `${value} km`;
      }
    },
    [locale],
  );

  return (
    <div dir={dir}>
      {/* Recherche + filtres — formulaire GET crawlable, intercepté côté client. */}
      <form
        method="GET"
        action="/restaurants"
        onSubmit={submitSearch}
        className="mb-6 grid gap-3 rounded-3xl border border-white/40 bg-white/80 p-4 shadow-sm backdrop-blur-xl sm:grid-cols-2 lg:grid-cols-5"
        role="search"
        aria-label={t(locale, 'restaurant.public.searchLabel')}
      >
        <label className="block space-y-1 lg:col-span-2">
          <span className="text-[10px] font-black uppercase tracking-widest text-slate-500">
            {t(locale, 'restaurant.public.searchLabel')}
          </span>
          <input
            type="search"
            name="q"
            value={q}
            onChange={(e) => setQ(e.target.value)}
            placeholder={t(locale, 'restaurant.public.searchPlaceholder')}
            className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-100"
          />
        </label>
        <label className="block space-y-1">
          <span className="text-[10px] font-black uppercase tracking-widest text-slate-500">
            {t(locale, 'restaurant.public.cityLabel')}
          </span>
          <input
            type="text"
            name="city"
            value={city}
            onChange={(e) => setCity(e.target.value)}
            placeholder={t(locale, 'restaurant.public.cityPlaceholder')}
            className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-100"
          />
        </label>
        <label className="block space-y-1">
          <span className="text-[10px] font-black uppercase tracking-widest text-slate-500">
            {t(locale, 'restaurant.public.typeLabel')}
          </span>
          <select
            name="type"
            value={type}
            onChange={(e) => setType(e.target.value)}
            className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 focus:border-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-100"
          >
            <option value="">{t(locale, 'restaurant.public.typeAll')}</option>
            {ESTABLISHMENT_TYPES.map((value) => (
              <option key={value} value={value}>
                {establishmentTypeLabel(locale, value)}
              </option>
            ))}
          </select>
        </label>
        <label className="block space-y-1">
          <span className="text-[10px] font-black uppercase tracking-widest text-slate-500">
            {t(locale, 'restaurant.public.cuisineLabel')}
          </span>
          <input
            type="text"
            name="cuisine"
            value={cuisine}
            onChange={(e) => setCuisine(e.target.value)}
            placeholder={t(locale, 'restaurant.public.cuisinePlaceholder')}
            className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-100"
          />
        </label>
        <div className="flex flex-wrap items-end gap-2 sm:col-span-2 lg:col-span-5">
          <button
            type="submit"
            className="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-amber-500 to-orange-600 px-4 py-2 text-sm font-black text-white shadow-md shadow-amber-500/20 hover:from-amber-600 hover:to-orange-700"
          >
            <Search className="h-4 w-4" aria-hidden="true" />
            {t(locale, 'restaurant.public.search')}
          </button>
          <button
            type="button"
            onClick={locateMe}
            disabled={geoState === 'locating'}
            className="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-700 hover:border-amber-300 disabled:opacity-50"
          >
            <LocateFixed className="h-4 w-4 text-amber-600" aria-hidden="true" />
            {geoState === 'locating'
              ? t(locale, 'restaurant.public.locating')
              : t(locale, 'restaurant.public.nearMe')}
          </button>
          {geoState === 'active' ? (
            <button
              type="button"
              onClick={clearNear}
              className="inline-flex items-center gap-1 rounded-xl px-3 py-2 text-xs font-bold text-slate-500 hover:bg-slate-100"
            >
              ✕ {t(locale, 'restaurant.public.clearNear')}
            </button>
          ) : null}
        </div>
        {geoState === 'denied' ? (
          <p className="text-xs font-medium text-rose-600 sm:col-span-2 lg:col-span-5" role="alert">
            {t(locale, 'restaurant.public.geoDenied')}
          </p>
        ) : null}
        {geoState === 'unsupported' ? (
          <p className="text-xs font-medium text-rose-600 sm:col-span-2 lg:col-span-5" role="alert">
            {t(locale, 'restaurant.public.geoUnsupported')}
          </p>
        ) : null}
        {geoState === 'active' ? (
          <p className="text-xs font-medium text-emerald-700 sm:col-span-2 lg:col-span-5" role="status">
            {t(locale, 'restaurant.public.nearActive')}
          </p>
        ) : null}
      </form>

      {/* Résultats */}
      {error ? (
        <div className="flex flex-col items-center gap-3 rounded-3xl border border-rose-200 bg-rose-50/80 p-10 text-center">
          <AlertTriangle className="h-8 w-8 text-rose-500" aria-hidden="true" />
          <p className="max-w-md text-sm font-bold text-rose-700">
            {t(locale, 'restaurant.public.loadError')}
          </p>
          <button
            type="button"
            onClick={() => void fetchResults({ q, city, type, cuisine, page: 1, near })}
            className="rounded-xl border border-rose-200 bg-white px-4 py-2 text-sm font-bold text-rose-600 hover:bg-rose-50"
          >
            {t(locale, 'restaurant.public.retry')}
          </button>
        </div>
      ) : loading ? (
        <p className="rounded-3xl border border-white/20 bg-white/70 p-10 text-center text-sm font-medium text-slate-500">
          {t(locale, 'restaurant.public.locating')}
        </p>
      ) : items.length === 0 ? (
        <p className="rounded-2xl border border-dashed border-slate-300 bg-white/50 p-10 text-center text-sm font-medium text-slate-500">
          {t(locale, 'restaurant.public.noResults')}
        </p>
      ) : (
        <ul className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3" data-testid="restaurant-results">
          {items.map((restaurant) => {
            const rating = formatRating(locale, restaurant.rating_avg);
            const typeLabel = establishmentTypeLabel(locale, restaurant.establishment_type);
            return (
              <li key={restaurant.slug}>
                <Link
                  href={`/restaurants/${restaurant.slug}`}
                  className="flex h-full flex-col overflow-hidden rounded-2xl border border-slate-200/70 bg-white/90 shadow-sm backdrop-blur-xl transition-shadow hover:shadow-md"
                >
                  {restaurant.cover_image_url ? (
                    // eslint-disable-next-line @next/next/no-img-element -- URL publique fournie par l'API, dimensions inconnues
                    <img
                      src={restaurant.cover_image_url}
                      alt={restaurant.name}
                      className="h-36 w-full object-cover"
                      loading="lazy"
                    />
                  ) : (
                    <div className="flex h-36 w-full items-center justify-center bg-gradient-to-br from-amber-100 to-orange-100">
                      <Store className="h-10 w-10 text-amber-500" aria-hidden="true" />
                    </div>
                  )}
                  <div className="flex flex-1 flex-col gap-1.5 p-4">
                    <p className="font-black tracking-tight text-slate-950">{restaurant.name}</p>
                    <p className="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-slate-500">
                      {typeLabel ? <span className="font-bold text-amber-700">{typeLabel}</span> : null}
                      {restaurant.city ? (
                        <span className="inline-flex items-center gap-0.5">
                          <MapPin className="h-3 w-3" aria-hidden="true" />
                          {restaurant.city}
                        </span>
                      ) : null}
                      {typeof restaurant.distance_km === 'number' ? (
                        <span className="font-bold text-emerald-700">
                          {distanceText(restaurant.distance_km)}
                        </span>
                      ) : null}
                    </p>
                    {restaurant.description ? (
                      <p className="line-clamp-2 text-xs text-slate-500">{restaurant.description}</p>
                    ) : null}
                    <p className="mt-auto flex items-center gap-1.5 pt-1 text-xs">
                      {rating ? (
                        <>
                          <Star className="h-3.5 w-3.5 text-amber-500" aria-hidden="true" />
                          <span className="font-black text-slate-800">{rating}</span>
                          <span className="text-slate-400">
                            ({restaurant.reviews_count} {t(locale, 'restaurant.public.reviewsSuffix')})
                          </span>
                        </>
                      ) : (
                        <span className="font-bold text-amber-700">
                          {t(locale, 'restaurant.public.viewMenu')} →
                        </span>
                      )}
                    </p>
                  </div>
                </Link>
              </li>
            );
          })}
        </ul>
      )}

      {/* Pagination — liens SSR crawlables hors proximité, boutons en mode near. */}
      {!error && lastPage > 1 ? (
        <nav
          className="mt-6 flex items-center justify-center gap-3 text-sm font-bold"
          aria-label={t(locale, 'restaurant.public.pageLabel')}
        >
          {near ? (
            <>
              <button
                type="button"
                disabled={page <= 1 || loading}
                onClick={() => void fetchResults({ q, city, type, cuisine, page: page - 1, near })}
                className="rounded-xl border border-slate-200 bg-white px-4 py-2 text-slate-700 hover:border-amber-300 disabled:opacity-40"
              >
                {t(locale, 'restaurant.public.previous')}
              </button>
              <span className="text-slate-500">
                {t(locale, 'restaurant.public.pageLabel')} {page} / {lastPage}
              </span>
              <button
                type="button"
                disabled={page >= lastPage || loading}
                onClick={() => void fetchResults({ q, city, type, cuisine, page: page + 1, near })}
                className="rounded-xl border border-slate-200 bg-white px-4 py-2 text-slate-700 hover:border-amber-300 disabled:opacity-40"
              >
                {t(locale, 'restaurant.public.next')}
              </button>
            </>
          ) : (
            <>
              {page > 1 ? (
                <Link
                  href={directoryHref({ q, city, type, cuisine, page: page - 1 })}
                  className="rounded-xl border border-slate-200 bg-white px-4 py-2 text-slate-700 hover:border-amber-300"
                >
                  {t(locale, 'restaurant.public.previous')}
                </Link>
              ) : null}
              <span className="text-slate-500">
                {t(locale, 'restaurant.public.pageLabel')} {page} / {lastPage}
              </span>
              {page < lastPage ? (
                <Link
                  href={directoryHref({ q, city, type, cuisine, page: page + 1 })}
                  className="rounded-xl border border-slate-200 bg-white px-4 py-2 text-slate-700 hover:border-amber-300"
                >
                  {t(locale, 'restaurant.public.next')}
                </Link>
              ) : null}
            </>
          )}
        </nav>
      ) : null}
    </div>
  );
}
