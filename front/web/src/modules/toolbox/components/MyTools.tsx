'use client';

import React, { useCallback, useEffect, useState } from 'react';
import Link from 'next/link';
import { Clock, Star, Trash2 } from 'lucide-react';
import { clearState, loadState, type ToolboxState } from '../lib/storage';

export interface MyToolsItem {
  slug: string;
  name: string;
  categoryLabel: string;
}

interface MyToolsProps {
  /** Outils du catalogue actif, résolus côté serveur (sérialisables). */
  items: MyToolsItem[];
  copy: {
    title: string;
    subtitle: string;
    favorites: string;
    history: string;
    empty: string;
    emptyHint: string;
    clear: string;
  };
}

/**
 * Espace personnel « Mes outils » — favoris et historique.
 *
 * Composant CLIENT, 100 % local : l'état vient de `lib/storage.ts`
 * (`localStorage`, décision #9156). Aucun compte, aucun appel réseau, aucune
 * donnée personnelle — seuls des slugs d'outils sont stockés.
 *
 * Le rendu initial est volontairement VIDE (état `null`) puis hydraté dans un
 * `useEffect` : le serveur ne peut pas lire `localStorage`, et rendre un état
 * différent au premier rendu client provoquerait une erreur d'hydratation.
 */
export function MyTools({ items, copy }: MyToolsProps) {
  const [state, setState] = useState<ToolboxState | null>(null);

  useEffect(() => {
    setState(loadState());
  }, []);

  const handleClear = useCallback(() => {
    setState(clearState());
  }, []);

  // Tant que l'hydratation n'a pas eu lieu, on ne rend rien : le catalogue
  // reste utilisable et aucun contenu serveur/client ne diverge.
  if (state === null) return null;

  const bySlug = new Map(items.map((item) => [item.slug, item]));
  const favorites = state.favorites.map((slug) => bySlug.get(slug)).filter(Boolean) as MyToolsItem[];
  const history = state.history.map((slug) => bySlug.get(slug)).filter(Boolean) as MyToolsItem[];

  const isEmpty = favorites.length === 0 && history.length === 0;

  return (
    <section aria-labelledby="my-tools-title" className="mb-10">
      <div className="flex flex-wrap items-start justify-between gap-3 mb-4">
        <div>
          <h2
            id="my-tools-title"
            className="text-xl font-bold text-slate-900 dark:text-white"
          >
            {copy.title}
          </h2>
          <p className="text-sm text-slate-600 dark:text-slate-400 mt-1">{copy.subtitle}</p>
        </div>
        {!isEmpty && (
          <button
            type="button"
            onClick={handleClear}
            className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg border border-slate-300 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:border-red-400 hover:text-red-600 dark:hover:text-red-400 transition-colors"
          >
            <Trash2 className="w-3.5 h-3.5" />
            {copy.clear}
          </button>
        )}
      </div>

      {isEmpty ? (
        <div className="rounded-2xl border border-dashed border-slate-300 dark:border-slate-700 px-5 py-6">
          <p className="text-sm font-medium text-slate-700 dark:text-slate-300">{copy.empty}</p>
          <p className="text-sm text-slate-500 dark:text-slate-400 mt-1">{copy.emptyHint}</p>
        </div>
      ) : (
        <div className="space-y-6">
          {favorites.length > 0 && (
            <div>
              <h3 className="flex items-center gap-1.5 text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-2">
                <Star className="w-3.5 h-3.5" />
                {copy.favorites}
              </h3>
              <ul className="flex flex-wrap gap-2">
                {favorites.map((item) => (
                  <li key={item.slug}>
                    <Link
                      href={`/toolbox/${item.slug}`}
                      className="inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium rounded-lg bg-emerald-50 dark:bg-emerald-950/40 text-emerald-800 dark:text-emerald-200 hover:bg-emerald-100 dark:hover:bg-emerald-950/70 transition-colors"
                    >
                      {item.name}
                      <span className="text-xs font-normal text-emerald-600 dark:text-emerald-400">
                        {item.categoryLabel}
                      </span>
                    </Link>
                  </li>
                ))}
              </ul>
            </div>
          )}

          {history.length > 0 && (
            <div>
              <h3 className="flex items-center gap-1.5 text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-2">
                <Clock className="w-3.5 h-3.5" />
                {copy.history}
              </h3>
              <ul className="flex flex-wrap gap-2">
                {history.map((item) => (
                  <li key={item.slug}>
                    <Link
                      href={`/toolbox/${item.slug}`}
                      className="inline-flex items-center px-3 py-1.5 text-sm font-medium rounded-lg border border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300 hover:border-emerald-400 dark:hover:border-emerald-600 transition-colors"
                    >
                      {item.name}
                    </Link>
                  </li>
                ))}
              </ul>
            </div>
          )}
        </div>
      )}
    </section>
  );
}
