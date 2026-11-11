'use client';

import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { MapPin, Navigation, Search, SlidersHorizontal } from 'lucide-react';
import type { PublicHomeCopy } from '../data/home-copy';
import type { PublicDiscoveryCategory } from '../data/discovery-categories';
import { PUBLIC_CARD, PUBLIC_TOUCH_TARGET } from '../data/design-tokens';

/**
 * Couloir 16 (M1) — recherche + localisation + filtres de l'accueil public.
 *
 * ## Pourquoi un formulaire qui pousse vers `/decouvrir`
 *
 * La recherche vit sur `/decouvrir`, page SSR indexable : le formulaire pousse
 * `?q=&city=&near=&open=&sort=` et laisse le serveur rendre le premier
 * résultat. Conséquences voulues : URL partageable et indexable, bouton
 * « retour » fonctionnel, aucun état client à hydrater.
 *
 * ## Aucune ville codée en dur
 *
 * Le sélecteur de ville est un champ libre. Une liste de villes supposerait un
 * référentiel qui n'existe pas côté API publique ; en inventer un serait
 * exactement le faux contenu que le cahier des charges interdit.
 *
 * ## Géolocalisation en deux temps
 *
 * « Autour de moi » demande la permission. En cas de refus, on bascule sur le
 * champ ville avec un message humain — jamais d'erreur technique. La position
 * est arrondie à 3 décimales (~110 m) : c'est la « position approximative par
 * défaut » exigée. La position précise n'est utilisée que si l'utilisateur la
 * redemande explicitement.
 *
 * ## Filtres conditionnels
 *
 * « Ouvert maintenant » n'apparaît que si `hasOpeningHours` (des horaires
 * publics existent réellement) ; « Plus proches » n'apparaît que si une
 * position est connue. Un filtre sans donnée derrière est un mensonge
 * d'interface.
 *
 * ## Cibles tactiles
 *
 * Tous les contrôles font au moins `PUBLIC_TOUCH_TARGET` (44 px), y compris
 * sur desktop : la souris n'excuse pas une cible trop petite pour un doigt.
 */

export interface HomeSearchState {
  q: string;
  city: string;
  near: string | null;
  openNow: boolean;
  sortByDistance: boolean;
}

interface HomeSearchProps {
  copy: PublicHomeCopy['hero'];
  filtersCopy: PublicHomeCopy['filters'];
  categories: readonly PublicDiscoveryCategory[];
  /** Vrai si au moins une fiche expose des horaires publics. */
  hasOpeningHours: boolean;
  /** Recherche initiale (restaurée depuis l'URL). */
  initial?: Partial<HomeSearchState>;
}

type GeoStatus = 'idle' | 'pending' | 'granted' | 'denied' | 'unsupported';

export function HomeSearch({
  copy,
  filtersCopy,
  categories,
  hasOpeningHours,
  initial,
}: HomeSearchProps) {
  const router = useRouter();
  const [q, setQ] = useState(initial?.q ?? '');
  const [city, setCity] = useState(initial?.city ?? '');
  const [near, setNear] = useState<string | null>(initial?.near ?? null);
  const [openNow, setOpenNow] = useState(initial?.openNow ?? false);
  const [sortByDistance, setSortByDistance] = useState(initial?.sortByDistance ?? false);
  const [geo, setGeo] = useState<GeoStatus>('idle');
  const [showFilters, setShowFilters] = useState(false);

  // Une demande de géolocalisation peut se résoudre APRÈS le démontage :
  // l'utilisateur met parfois plusieurs secondes à répondre à la bannière.
  // Sans ce garde, on écrit dans l'état d'un composant démonté.
  const mounted = useRef(true);
  useEffect(() => {
    mounted.current = true;
    return () => {
      mounted.current = false;
    };
  }, []);

  const requestLocation = useCallback(() => {
    if (typeof navigator === 'undefined' || !navigator.geolocation) {
      setGeo('unsupported');
      return;
    }

    setGeo('pending');
    navigator.geolocation.getCurrentPosition(
      (position) => {
        if (!mounted.current) return;
        const lat = position.coords.latitude.toFixed(3);
        const lng = position.coords.longitude.toFixed(3);
        setNear(`${lat},${lng}`);
        setGeo('granted');
        setCity('');
      },
      () => {
        if (!mounted.current) return;
        setGeo('denied');
        setNear(null);
      },
      { enableHighAccuracy: false, timeout: 8000, maximumAge: 300_000 },
    );
  }, []);

  const submit = (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const params = new URLSearchParams();
    const trimmedQ = q.trim();
    const trimmedCity = city.trim();

    if (trimmedQ) params.set('q', trimmedQ);
    if (trimmedCity) params.set('city', trimmedCity);
    if (near) params.set('near', near);
    if (openNow) params.set('open', '1');
    if (sortByDistance && near) params.set('sort', 'distance');

    const qs = params.toString();
    router.push(qs ? `/decouvrir?${qs}` : '/decouvrir');
  };

  const activeFilterCount = useMemo(
    () => (openNow ? 1 : 0) + (sortByDistance && near ? 1 : 0),
    [openNow, sortByDistance, near],
  );

  const cityPlaceholder = geo === 'denied' ? copy.locationDenied : copy.cityPlaceholder;

  return (
    <form role="search" onSubmit={submit} className="mt-8 w-full max-w-3xl">
      {/* ── Barre de recherche ─────────────────────────────────────────── */}
      <div className="rounded-xl border border-slate-200 bg-white p-2 shadow-sm dark:border-white/10 dark:bg-white/[0.04]">
        <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
          <div className="flex min-w-0 flex-1 items-center gap-2 px-3">
            <Search className="h-4 w-4 shrink-0 text-slate-400" aria-hidden />
            <label htmlFor="home-search-q" className="sr-only">
              {copy.searchLabel}
            </label>
            <input
              id="home-search-q"
              name="q"
              type="search"
              value={q}
              onChange={(event) => setQ(event.target.value)}
              placeholder={copy.searchPlaceholder}
              maxLength={120}
              autoComplete="off"
              className="w-full min-w-0 bg-transparent text-sm text-slate-900 outline-none placeholder:text-slate-400 dark:text-white dark:placeholder:text-slate-500"
              style={{ height: PUBLIC_CARD.searchHeight - 16 }}
            />
          </div>

          <div
            className="hidden h-8 w-px shrink-0 bg-slate-200 sm:block dark:bg-white/10"
            aria-hidden
          />

          {/* Localisation : position connue ⇒ libellé, sinon champ ville. */}
          <div className="flex min-w-0 items-center gap-1.5 px-3 sm:w-56">
            <MapPin className="h-4 w-4 shrink-0 text-slate-400" aria-hidden />
            {near ? (
              <span className="flex min-w-0 flex-1 items-center gap-1 text-sm font-semibold text-emerald-700 dark:text-emerald-400">
                <span className="truncate">{copy.aroundMe}</span>
                <button
                  type="button"
                  onClick={() => {
                    setNear(null);
                    setGeo('idle');
                    setSortByDistance(false);
                  }}
                  className="ml-auto shrink-0 rounded px-1 text-xs font-medium text-slate-500 underline-offset-2 hover:underline dark:text-slate-400"
                >
                  {copy.changeLocation}
                </button>
              </span>
            ) : (
              <>
                <label htmlFor="home-search-city" className="sr-only">
                  {copy.cityLabel}
                </label>
                <input
                  id="home-search-city"
                  name="city"
                  type="text"
                  value={city}
                  onChange={(event) => setCity(event.target.value)}
                  placeholder={cityPlaceholder}
                  maxLength={120}
                  autoComplete="address-level2"
                  className="w-full min-w-0 bg-transparent text-sm text-slate-900 outline-none placeholder:text-slate-400 dark:text-white dark:placeholder:text-slate-500"
                  style={{ height: PUBLIC_CARD.searchHeight - 16 }}
                />
              </>
            )}
          </div>

          <button
            type="submit"
            className="inline-flex shrink-0 items-center justify-center gap-2 rounded-lg bg-emerald-600 px-5 text-sm font-bold text-white transition-colors hover:bg-emerald-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
            style={{ height: PUBLIC_TOUCH_TARGET }}
          >
            <Search className="h-4 w-4 sm:hidden" aria-hidden />
            {copy.searchCta}
          </button>
        </div>
      </div>

      {/* ── Localisation + filtres ─────────────────────────────────────── */}
      <div className="mt-3 flex flex-wrap items-center gap-2">
        {!near && (
          <button
            type="button"
            onClick={requestLocation}
            disabled={geo === 'pending'}
            className="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 transition-colors hover:border-slate-400 disabled:opacity-60 dark:border-white/15 dark:bg-white/5 dark:text-slate-200"
            style={{ minHeight: PUBLIC_TOUCH_TARGET }}
          >
            <Navigation className="h-3.5 w-3.5" aria-hidden />
            {geo === 'pending' ? copy.locating : copy.aroundMe}
          </button>
        )}

        {(hasOpeningHours || near) && (
          <button
            type="button"
            onClick={() => setShowFilters((value) => !value)}
            aria-expanded={showFilters}
            aria-controls="home-filters"
            className="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 transition-colors hover:border-slate-400 dark:border-white/15 dark:bg-white/5 dark:text-slate-200"
            style={{ minHeight: PUBLIC_TOUCH_TARGET }}
          >
            <SlidersHorizontal className="h-3.5 w-3.5" aria-hidden />
            {filtersCopy.title}
            {activeFilterCount > 0 && (
              <span className="ml-0.5 rounded-full bg-emerald-600 px-1.5 text-[11px] font-bold text-white">
                {activeFilterCount}
              </span>
            )}
          </button>
        )}

        {geo === 'denied' && (
          <p className="text-xs text-slate-500 dark:text-slate-400">{copy.locationDeniedHint}</p>
        )}
      </div>

      {showFilters && (
        <div
          id="home-filters"
          className="mt-2 flex flex-wrap gap-2 rounded-xl border border-slate-200 bg-slate-50/70 p-3 dark:border-white/10 dark:bg-white/[0.03]"
        >
          {hasOpeningHours && (
            <label
              className="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-700 dark:border-white/10 dark:bg-white/5 dark:text-slate-200"
              style={{ minHeight: PUBLIC_TOUCH_TARGET }}
            >
              <input
                type="checkbox"
                checked={openNow}
                onChange={(event) => setOpenNow(event.target.checked)}
                className="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-600"
              />
              {filtersCopy.openNow}
            </label>
          )}

          {near && (
            <label
              className="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-700 dark:border-white/10 dark:bg-white/5 dark:text-slate-200"
              style={{ minHeight: PUBLIC_TOUCH_TARGET }}
            >
              <input
                type="checkbox"
                checked={sortByDistance}
                onChange={(event) => setSortByDistance(event.target.checked)}
                className="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-600"
              />
              {filtersCopy.nearest}
            </label>
          )}
        </div>
      )}

      {/* ── Catégories (source : /public/discovery/categories) ─────────── */}
      {categories.length > 0 && (
        <ul className="mt-4 flex flex-wrap gap-2">
          {categories.map((category) => (
            <li key={category.key}>
              <Link
                href={`/decouvrir?type=${encodeURIComponent(category.key)}`}
                className="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-700 transition-colors hover:border-emerald-300 hover:text-emerald-800 dark:border-white/10 dark:bg-white/5 dark:text-slate-200 dark:hover:border-emerald-500/40"
                style={{ minHeight: PUBLIC_TOUCH_TARGET }}
              >
                {category.label}
                <span className="text-[11px] font-normal text-slate-400">{category.count}</span>
              </Link>
            </li>
          ))}
        </ul>
      )}
    </form>
  );
}
