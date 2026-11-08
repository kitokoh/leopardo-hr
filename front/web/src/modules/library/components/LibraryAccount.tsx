'use client';

import React, { useEffect, useState } from 'react';
import Link from 'next/link';
import { BookOpen, Heart } from 'lucide-react';
import { loadFavorites, loadProgress, progressPercent, type ReadingProgress } from '../lib/progress';

interface LibraryAccountProps {
  /** Titres indexés par slug (résolus côté serveur). */
  titles: Record<string, string>;
  copy: {
    title: string;
    favorites: string;
    recentReads: string;
    progress: string;
    empty: string;
    signInHint: string;
    page: string;
    of: string;
  };
}

/**
 * Espace utilisateur — favoris et lectures récentes.
 *
 * Composant CLIENT : les données proviennent de `localStorage` (visiteurs
 * anonymes). Aucune création de compte n'est requise pour consulter les
 * ressources publiques gratuites ; la synchronisation serveur viendra avec
 * l'API (même clé de stockage).
 */
export function LibraryAccount({ titles, copy }: LibraryAccountProps) {
  const [favorites, setFavorites] = useState<string[]>([]);
  const [progress, setProgress] = useState<ReadingProgress[]>([]);

  useEffect(() => {
    setFavorites(loadFavorites());
    setProgress(loadProgress());
  }, []);

  return (
    <div className="space-y-8">
      <section>
        <h2 className="flex items-center gap-2 text-lg font-bold text-slate-900 dark:text-white mb-3">
          <Heart className="w-5 h-5 text-rose-500" />
          {copy.favorites}
        </h2>
        {favorites.length === 0 ? (
          <p className="text-sm text-slate-500 dark:text-slate-400">{copy.empty}</p>
        ) : (
          <ul className="grid gap-3 sm:grid-cols-2">
            {favorites.map((slug) => (
              <li key={slug}>
                <Link
                  href={`/library/${slug}`}
                  className="block rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-4 py-3 text-sm font-semibold text-slate-900 dark:text-white hover:border-emerald-400"
                >
                  {titles[slug] ?? slug}
                </Link>
              </li>
            ))}
          </ul>
        )}
      </section>

      <section>
        <h2 className="flex items-center gap-2 text-lg font-bold text-slate-900 dark:text-white mb-3">
          <BookOpen className="w-5 h-5 text-emerald-600" />
          {copy.recentReads}
        </h2>
        {progress.length === 0 ? (
          <p className="text-sm text-slate-500 dark:text-slate-400">{copy.empty}</p>
        ) : (
          <ul className="space-y-3">
            {progress.map((entry) => (
              <li
                key={entry.slug}
                className="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-4 py-3"
              >
                <div className="flex items-center justify-between gap-3">
                  <Link
                    href={`/library/${entry.slug}`}
                    className="text-sm font-semibold text-slate-900 dark:text-white hover:text-emerald-700"
                  >
                    {titles[entry.slug] ?? entry.slug}
                  </Link>
                  <span className="text-xs text-slate-500 tabular-nums">
                    {copy.page} {entry.page} {copy.of} {entry.totalPages}
                  </span>
                </div>
                <div className="mt-2 h-1.5 w-full rounded-full bg-slate-200 dark:bg-slate-800 overflow-hidden">
                  <div
                    className="h-full bg-emerald-500"
                    style={{ width: `${progressPercent(entry.page, entry.totalPages)}%` }}
                  />
                </div>
              </li>
            ))}
          </ul>
        )}
      </section>

      <p className="text-xs text-slate-500 dark:text-slate-400">{copy.signInHint}</p>
    </div>
  );
}
