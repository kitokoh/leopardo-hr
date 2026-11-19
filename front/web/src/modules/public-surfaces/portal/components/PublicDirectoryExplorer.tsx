'use client';

import { useState } from 'react';
import Link from 'next/link';
import { AlertTriangle, LocateFixed, MapPin, Search } from 'lucide-react';
import { apiFetch } from '@/lib/api-client';
import type { AppLocale } from '@/lib/i18n';
import { catalogDirection } from '@/lib/i18n/locale-catalog';
import {
  buildDiscoveryQuery,
  type PublicDiscoveryItem,
  type PublicDiscoveryMeta,
} from '@/lib/public-discovery-api';
import { getPublicPortalCopy, publicTypeLabel } from '../data/copy';

/**
 * Explorateur d'annuaire générique du portail public.
 *
 * ## Pourquoi un composant unique
 *
 * `/boutiques`, `/operateurs`, `/entreprises` et `/stay` sont la MÊME page :
 * un annuaire filtré sur un type canonique de `/public/discover`. Avant, seule
 * `/restaurants` existait (avec son propre explorateur) et les quatre autres
 * verticales n'avaient AUCUNE page d'index — leurs fiches `/{verticale}/{slug}`
 * étaient donc inatteignables depuis le portail, et absentes du sitemap.
 *
 * Ce composant ne duplique aucune logique métier : il interroge le même
 * contrat public que `/decouvrir` (`GET /api/v1/public/discover`), avec
 * `type` figé par la page appelante.
 *
 * ## SSR et crawlabilité
 *
 * Le premier rendu vient du Server Component (props `initialItems`), donc le
 * HTML est indexable. Hors proximité, la pagination reste des liens `<a>`
 * crawlables ; en mode « Autour de moi » elle passe en boutons (la position
 * n'est jamais mise dans l'URL partageable).
 */

interface PublicDirectoryExplorerProps {
  locale: AppLocale;
  /** Type canonique figé de la verticale (`retail_shop`, `vtc_operator`, …). */
  type: string;
  /** Chemin de l'annuaire, pour construire les liens de pagination. */
  basePath: string;
  initialItems: PublicDiscoveryItem[];
  initialMeta: PublicDiscoveryMeta | null;
  /** true si le fetch SSR initial a échoué (API indisponible). */
  initialError: boolean;
  initialQuery: { q: string; city: string; page: number };
}

type GeoState = 'idle' | 'locating' | 'active' | 'denied' | 'unsupported';

export default function PublicDirectoryExplorer({
  locale,
  type,
  basePath,
  initialItems,
  initialMeta,
  initialError,
  initialQuery,
}: PublicDirectoryExplorerProps) {
  const copy = getPublicPortalCopy(locale);
  const dir = catalogDirection(locale);

  const [q, setQ] = useState(initialQuery.q);
  const [city, setCity] = useState(initialQuery.city);
  const [items, setItems] = useState(initialItems);
  const [meta, setMeta] = useState(initialMeta);
  const [page, setPage] = useState(initialQuery.page);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(initialError);
  const [geoState, setGeoState] = useState<GeoState>('idle');
  const [near, setNear] = useState<string | null>(null);

  const directoryHref = (params: { q: string; city: string; page: number }): string => {
    const qs = buildDiscoveryQuery({
      q: params.q,
      city: params.city,
      type,
      page: params.page,
    });
    return qs ? `${basePath}?${qs}` : basePath;
  };

  const fetchResults = async (params: {
    q: string;
    city: string;
    page: number;
    near: string | null;
  }) => {
    setLoading(true);
    setError(false);
    try {
      const qs = buildDiscoveryQuery({
        q: params.q,
        city: params.city,
        type,
        page: params.page,
        near: params.near ?? undefined,
      });
      const res = await apiFetch(`/public/discover${qs ? `?${qs}` : ''}`, { _cacheBust: true });
      if (!res.ok) {
        throw new Error(String(res.status));
      }
      const json = (await res.json()) as {
        data?: PublicDiscoveryItem[];
        meta?: PublicDiscoveryMeta;
      };
      setItems(Array.isArray(json.data) ? json.data : []);
      setMeta(json.meta ?? null);
      setPage(params.page);
      if (typeof window !== 'undefined' && !params.near) {
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
    void fetchResults({ q, city, page: 1, near });
  };

  const locateMe = () => {
    if (typeof navigator === 'undefined' || !navigator.geolocation) {
      setGeoState('unsupported');
      return;
    }
    setGeoState('locating');
    navigator.geolocation.getCurrentPosition(
      (position) => {
        const coords = `${position.coords.latitude.toFixed(5)},${position.coords.longitude.toFixed(5)}`;
        setNear(coords);
        setGeoState('active');
        void fetchResults({ q, city, page: 1, near: coords });
      },
      () => setGeoState('denied'),
      { enableHighAccuracy: false, timeout: 8000, maximumAge: 300000 },
    );
  };

  const clearNear = () => {
    setNear(null);
    setGeoState('idle');
    void fetchResults({ q, city, page: 1, near: null });
  };

  const lastPage = meta?.last_page ?? 1;

  return (
    <div dir={dir}>
      <form onSubmit={submitSearch} className="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <label className="flex flex-col gap-1 text-xs font-semibold text-slate-600">
          {copy.directory.searchLabel}
          <input
            type="search"
            value={q}
            onChange={(event) => setQ(event.target.value)}
            placeholder={copy.directory.searchPlaceholder}
            maxLength={120}
            className="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-normal text-slate-900"
          />
        </label>

        <label className="flex flex-col gap-1 text-xs font-semibold text-slate-600">
          {copy.directory.cityLabel}
          <input
            type="text"
            value={city}
            onChange={(event) => setCity(event.target.value)}
            placeholder={copy.directory.cityPlaceholder}
            maxLength={120}
            className="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-normal text-slate-900"
          />
        </label>

        <div className="flex items-end gap-2 sm:col-span-2">
          <button
            type="submit"
            className="inline-flex flex-1 items-center justify-center gap-2 rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white"
          >
            <Search className="h-4 w-4" aria-hidden />
            {copy.directory.submit}
          </button>
          <button
            type="button"
            onClick={locateMe}
            className="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700"
          >
            <LocateFixed className="h-4 w-4" aria-hidden />
            {geoState === 'locating' ? copy.directory.locating : copy.directory.nearMe}
          </button>
        </div>
      </form>

      {geoState === 'denied' && (
        <p role="status" className="mb-4 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800">
          {copy.directory.geoDenied}
        </p>
      )}
      {geoState === 'unsupported' && (
        <p role="status" className="mb-4 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800">
          {copy.directory.geoUnsupported}
        </p>
      )}
      {geoState === 'active' && (
        <p className="mb-4 flex items-center gap-2 text-sm text-slate-600">
          <MapPin className="h-4 w-4" aria-hidden />
          {copy.directory.nearActive}
          <button type="button" onClick={clearNear} className="font-semibold underline">
            {copy.directory.clearNear}
          </button>
        </p>
      )}

      {loading && (
        <p className="py-8 text-center text-sm text-slate-500">{copy.directory.loading}</p>
      )}

      {!loading && error && (
        <div className="rounded-xl border border-red-200 bg-red-50 p-6 text-center">
          <AlertTriangle className="mx-auto mb-2 h-6 w-6 text-red-600" aria-hidden />
          <p className="text-sm text-red-800">{copy.directory.loadError}</p>
          <button
            type="button"
            onClick={() => void fetchResults({ q, city, page, near })}
            className="mt-3 rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white"
          >
            {copy.directory.retry}
          </button>
        </div>
      )}

      {!loading && !error && items.length === 0 && (
        <div className="rounded-xl border border-slate-200 bg-white p-8 text-center">
          <p className="text-sm text-slate-600">{copy.directory.noResults}</p>
          <p className="mt-1 text-xs text-slate-400">{copy.directory.emptyHint}</p>
        </div>
      )}

      {!loading && !error && items.length > 0 && (
        <>
          <p className="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-500">
            {meta?.total ?? items.length} {copy.directory.resultsCount}
          </p>
          <ul className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {items.map((item) => (
              <li
                key={`${item.type}-${item.slug}`}
                className="flex flex-col rounded-xl border border-slate-200 bg-white p-4 shadow-sm"
              >
                <span className="mb-1 text-xs font-bold uppercase tracking-wide text-amber-700">
                  {publicTypeLabel(locale, item.type)}
                </span>
                <h2 className="text-base font-bold text-slate-950">{item.name}</h2>
                {item.category && <p className="text-xs text-slate-500">{item.category}</p>}
                {item.city && (
                  <p className="mt-1 flex items-center gap-1 text-xs text-slate-500">
                    <MapPin className="h-3 w-3" aria-hidden />
                    {item.city}
                  </p>
                )}
                {typeof item.distance_km === 'number' && (
                  <p className="mt-1 text-xs font-semibold text-slate-600">
                    {item.distance_km} {copy.directory.distanceSuffix}
                  </p>
                )}
                {item.description && (
                  <p className="mt-2 line-clamp-3 text-sm text-slate-600">{item.description}</p>
                )}

                {item.url && (
                  <Link
                    href={item.url}
                    className="mt-4 inline-flex w-fit rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white"
                  >
                    {copy.directory.viewProfile}
                  </Link>
                )}
              </li>
            ))}
          </ul>

          {lastPage > 1 && (
            <nav
              aria-label={copy.directory.pageLabel}
              className="mt-6 flex items-center justify-center gap-3 text-sm"
            >
              {near ? (
                <button
                  type="button"
                  disabled={page <= 1}
                  onClick={() => void fetchResults({ q, city, page: page - 1, near })}
                  className="rounded-lg border border-slate-300 px-3 py-1.5 font-semibold disabled:opacity-40"
                >
                  {copy.directory.previous}
                </button>
              ) : (
                page > 1 && (
                  <Link
                    href={directoryHref({ q, city, page: page - 1 })}
                    className="rounded-lg border border-slate-300 px-3 py-1.5 font-semibold"
                  >
                    {copy.directory.previous}
                  </Link>
                )
              )}

              <span className="text-slate-500">
                {copy.directory.pageLabel} {page} / {lastPage}
              </span>

              {near ? (
                <button
                  type="button"
                  disabled={page >= lastPage}
                  onClick={() => void fetchResults({ q, city, page: page + 1, near })}
                  className="rounded-lg border border-slate-300 px-3 py-1.5 font-semibold disabled:opacity-40"
                >
                  {copy.directory.next}
                </button>
              ) : (
                page < lastPage && (
                  <Link
                    href={directoryHref({ q, city, page: page + 1 })}
                    className="rounded-lg border border-slate-300 px-3 py-1.5 font-semibold"
                  >
                    {copy.directory.next}
                  </Link>
                )
              )}
            </nav>
          )}
        </>
      )}
    </div>
  );
}
