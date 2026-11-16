'use client';

import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Search, X } from 'lucide-react';
import { ToolCard } from './ToolCard';
import {
  ALL_CATEGORIES,
  EMPTY_CATALOG_QUERY,
  buildCatalogQuery,
  hasActiveFilters,
  parseCatalogQuery,
  type CatalogQueryState,
} from '../lib/catalog-query';
import type { ToolboxLocale } from '../data/copy';

export interface CatalogItem {
  slug: string;
  name: string;
  description: string;
  category: string;
  categoryLabel: string;
  keywords: string[];
}

interface ToolboxCatalogProps {
  items: CatalogItem[];
  categories: { id: string; label: string }[];
  /** Locale courante — propagée dans chaque carte d'outil. */
  locale: ToolboxLocale;
  copy: {
    searchPlaceholder: string;
    noResults: string;
    allCategories: string;
    openTool: string;
    toolsCount: string;
    /** Libellé du bouton qui remet le catalogue à zéro. */
    clearFilters: string;
  };
}

/** Minuscules sans diacritiques — recherche insensible à la casse/accents. */
function normalize(value: string): string {
  return value
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .trim();
}

/**
 * Catalogue Toolbox — recherche plein-texte + filtre par catégorie.
 *
 * Composant CLIENT : le filtrage est instantané et 100 % local (aucun appel
 * réseau). Les données sont sérialisables (passées depuis le composant serveur).
 *
 * ## État dans l'URL (`?q=…&cat=…`)
 *
 * L'état vivait auparavant dans deux `useState` : un catalogue filtré n'était
 * ni partageable, ni retrouvé au retour arrière, et le sélecteur de langue
 * perdait le filtre en changeant de langue. Il est désormais persisté dans
 * l'URL via `lib/catalog-query.ts`.
 *
 * Choix d'implémentation (mêmes règles que `useToolState`) :
 * - l'URL est lue **au montage**, jamais au rendu serveur : `window` n'existe
 *   pas côté serveur, et lire l'URL au premier rendu client casserait
 *   l'hydratation. On n'utilise donc pas `useSearchParams`, qui imposerait en
 *   plus une frontière `Suspense` sur une page générée statiquement ;
 * - l'URL est écrite avec `history.replaceState` (pas `pushState`) : filtrer
 *   ne doit pas empiler une entrée d'historique par frappe ;
 * - l'écriture n'a lieu qu'après une **première interaction** : ouvrir le
 *   catalogue ne réécrit pas l'URL avec des valeurs par défaut ;
 * - `popstate` est écouté : le retour arrière restaure le filtre précédent.
 */
export function ToolboxCatalog({ items, categories, locale, copy }: ToolboxCatalogProps) {
  const [state, setState] = useState<CatalogQueryState>(EMPTY_CATALOG_QUERY);
  const hydrated = useRef(false);
  const dirty = useRef(false);

  // Hydratation depuis l'URL — une seule fois, après le montage.
  useEffect(() => {
    if (hydrated.current) return;
    hydrated.current = true;

    if (typeof window === 'undefined') return;
    setState(parseCatalogQuery(window.location.search));
  }, []);

  // Retour arrière / avant : l'URL redevient la source de vérité.
  useEffect(() => {
    if (typeof window === 'undefined') return;

    const onPopState = () => {
      setState(parseCatalogQuery(window.location.search));
    };

    window.addEventListener('popstate', onPopState);
    return () => window.removeEventListener('popstate', onPopState);
  }, []);

  // Réécriture de l'URL — uniquement après une interaction de l'utilisateur.
  useEffect(() => {
    if (!dirty.current) return;
    if (typeof window === 'undefined') return;

    try {
      const qs = buildCatalogQuery(state, window.location.search);
      const url = qs === '' ? window.location.pathname : `${window.location.pathname}?${qs}`;
      window.history.replaceState(window.history.state, '', url);
    } catch {
      // URL non constructible : l'état reste en mémoire, rien n'échoue.
    }
  }, [state]);

  const setQuery = useCallback((query: string) => {
    dirty.current = true;
    setState((previous) => ({ ...previous, query }));
  }, []);

  const setCategory = useCallback((category: string) => {
    dirty.current = true;
    setState((previous) => ({ ...previous, category }));
  }, []);

  const clearFilters = useCallback(() => {
    dirty.current = true;
    setState({ ...EMPTY_CATALOG_QUERY });
  }, []);

  const filtered = useMemo(() => {
    const needle = normalize(state.query);
    return items.filter((item) => {
      if (state.category !== ALL_CATEGORIES && item.category !== state.category) return false;
      if (needle === '') return true;
      const haystack = normalize([item.name, item.description, item.slug, ...item.keywords].join(' '));
      return haystack.includes(needle);
    });
  }, [items, state.query, state.category]);

  const filtersActive = hasActiveFilters(state);

  return (
    <div>
      <div className="flex flex-col sm:flex-row gap-3 mb-6">
        <div className="relative flex-1">
          <Search className="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
          <input
            type="search"
            value={state.query}
            onChange={(event) => setQuery(event.target.value)}
            placeholder={copy.searchPlaceholder}
            aria-label={copy.searchPlaceholder}
            className="w-full pl-10 pr-4 py-2.5 text-sm font-medium bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
          />
        </div>
        <select
          value={state.category}
          onChange={(event) => setCategory(event.target.value)}
          aria-label={copy.allCategories}
          className="px-4 py-2.5 text-sm font-medium bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500"
        >
          <option value={ALL_CATEGORIES}>{copy.allCategories}</option>
          {categories.map((entry) => (
            <option key={entry.id} value={entry.id}>
              {entry.label}
            </option>
          ))}
        </select>
        {filtersActive && (
          <button
            type="button"
            onClick={clearFilters}
            className="inline-flex items-center justify-center gap-1.5 px-3 py-2.5 text-sm font-semibold rounded-xl border border-slate-300 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:border-emerald-400 hover:text-emerald-700 dark:hover:text-emerald-300 transition-colors"
          >
            <X className="w-4 h-4" />
            {copy.clearFilters}
          </button>
        )}
      </div>

      {/*
        Le compteur change à chaque frappe : sans `aria-live`, un lecteur
        d'écran n'annonce jamais que la liste s'est réduite (ou vidée).
        `polite` (et non `assertive`) : on ne coupe pas la saisie en cours.
      */}
      <p
        aria-live="polite"
        aria-atomic="true"
        className="text-sm text-slate-500 dark:text-slate-400 mb-4"
      >
        {filtered.length} {copy.toolsCount}
      </p>

      {filtered.length === 0 ? (
        <p className="text-sm text-slate-500 dark:text-slate-400 py-8 text-center">{copy.noResults}</p>
      ) : (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {filtered.map((item) => (
            <ToolCard
              key={item.slug}
              href={`/toolbox/${item.slug}`}
              name={item.name}
              description={item.description}
              categoryLabel={item.categoryLabel}
              openLabel={copy.openTool}
              locale={locale}
            />
          ))}
        </div>
      )}
    </div>
  );
}
