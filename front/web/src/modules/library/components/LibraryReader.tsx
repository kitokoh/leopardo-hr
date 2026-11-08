'use client';

import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
  ChevronLeft,
  ChevronRight,
  Maximize2,
  Minimize2,
  Search,
  X,
  ZoomIn,
  ZoomOut,
} from 'lucide-react';
import { clampProgress, progressPercent, saveProgress, type ReadingProgress } from '../lib/progress';

interface LibraryReaderProps {
  slug: string;
  title: string;
  /** Contenu texte paginé (œuvres courtes du domaine public). */
  pages: string[];
  /** Progression initiale (reprise de lecture). */
  initialProgress?: ReadingProgress | null;
  copy: {
    title: string;
    page: string;
    of: string;
    zoomIn: string;
    zoomOut: string;
    fullscreen: string;
    exitFullscreen: string;
    search: string;
    searchPlaceholder: string;
    noMatches: string;
    resume: string;
    lastPosition: string;
    close: string;
  };
}

/**
 * Lecteur numérique — navigation entre pages, zoom, plein écran, recherche
 * dans le document, reprise de lecture et enregistrement de la position.
 *
 * Le contenu est fourni paginé (texte). Aucun fichier complet n'est téléchargé
 * automatiquement : la lecture se fait en ligne, page par page.
 */
export function LibraryReader({ slug, title, pages, initialProgress, copy }: LibraryReaderProps) {
  const total = pages.length;
  const [page, setPage] = useState(() =>
    initialProgress ? clampProgress(initialProgress.page, total) : 1,
  );
  const [zoom, setZoom] = useState(1);
  const [fullscreen, setFullscreen] = useState(false);
  const [searchOpen, setSearchOpen] = useState(false);
  const [query, setQuery] = useState('');
  const containerRef = useRef<HTMLDivElement>(null);

  const goTo = useCallback(
    (next: number) => {
      const clamped = clampProgress(next, total);
      setPage(clamped);
      saveProgress({ slug, page: clamped, totalPages: total, updatedAt: Date.now() });
    },
    [slug, total],
  );

  // Enregistre la position à l'ouverture (reprise de lecture).
  useEffect(() => {
    saveProgress({ slug, page, totalPages: total, updatedAt: Date.now() });
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const matches = useMemo(() => {
    const needle = query.trim().toLowerCase();
    if (needle === '') return [];
    return pages
      .map((text, index) => ({ index, text }))
      .filter((entry) => entry.text.toLowerCase().includes(needle));
  }, [pages, query]);

  const percent = progressPercent(page, total);

  return (
    <div
      ref={containerRef}
      className={
        fullscreen
          ? 'fixed inset-0 z-50 bg-white dark:bg-slate-950 flex flex-col'
          : 'rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 flex flex-col'
      }
    >
      {/* Barre d'outils */}
      <div className="flex flex-wrap items-center gap-2 border-b border-slate-200 dark:border-slate-800 px-4 py-3">
        <span className="text-sm font-bold text-slate-900 dark:text-white mr-auto">{title}</span>

        <button
          type="button"
          onClick={() => setZoom((z) => Math.max(0.75, +(z - 0.25).toFixed(2)))}
          aria-label={copy.zoomOut}
          className="p-2 rounded-lg border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800"
        >
          <ZoomOut className="w-4 h-4" />
        </button>
        <span className="text-xs font-semibold text-slate-500 tabular-nums w-10 text-center">
          {Math.round(zoom * 100)}%
        </span>
        <button
          type="button"
          onClick={() => setZoom((z) => Math.min(2, +(z + 0.25).toFixed(2)))}
          aria-label={copy.zoomIn}
          className="p-2 rounded-lg border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800"
        >
          <ZoomIn className="w-4 h-4" />
        </button>

        <button
          type="button"
          onClick={() => setSearchOpen((open) => !open)}
          aria-label={copy.search}
          className="p-2 rounded-lg border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800"
        >
          <Search className="w-4 h-4" />
        </button>

        <button
          type="button"
          onClick={() => setFullscreen((value) => !value)}
          aria-label={fullscreen ? copy.exitFullscreen : copy.fullscreen}
          className="p-2 rounded-lg border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800"
        >
          {fullscreen ? <Minimize2 className="w-4 h-4" /> : <Maximize2 className="w-4 h-4" />}
        </button>
      </div>

      {/* Recherche dans le document */}
      {searchOpen && (
        <div className="border-b border-slate-200 dark:border-slate-800 px-4 py-3">
          <div className="flex items-center gap-2">
            <input
              type="search"
              value={query}
              onChange={(event) => setQuery(event.target.value)}
              placeholder={copy.searchPlaceholder}
              aria-label={copy.search}
              className="flex-1 px-3 py-2 text-sm bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500"
            />
            <button
              type="button"
              onClick={() => {
                setSearchOpen(false);
                setQuery('');
              }}
              aria-label={copy.close}
              className="p-2 rounded-lg text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800"
            >
              <X className="w-4 h-4" />
            </button>
          </div>
          {query.trim() !== '' && (
            <div className="mt-2 text-xs text-slate-500 dark:text-slate-400">
              {matches.length === 0 ? (
                <span>{copy.noMatches}</span>
              ) : (
                <ul className="flex flex-wrap gap-2">
                  {matches.map((match) => (
                    <li key={match.index}>
                      <button
                        type="button"
                        onClick={() => goTo(match.index + 1)}
                        className="px-2 py-1 rounded-md bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 font-semibold"
                      >
                        {copy.page} {match.index + 1}
                      </button>
                    </li>
                  ))}
                </ul>
              )}
            </div>
          )}
        </div>
      )}

      {/* Contenu */}
      <div className="flex-1 overflow-auto px-6 py-6">
        <div
          className="mx-auto max-w-2xl whitespace-pre-line text-slate-800 dark:text-slate-200 leading-relaxed"
          style={{ fontSize: `${zoom}rem` }}
        >
          {pages[page - 1]}
        </div>
      </div>

      {/* Navigation */}
      <div className="flex items-center justify-between gap-3 border-t border-slate-200 dark:border-slate-800 px-4 py-3">
        <button
          type="button"
          onClick={() => goTo(page - 1)}
          disabled={page <= 1}
          className="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-semibold rounded-lg border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 disabled:opacity-40 hover:bg-slate-100 dark:hover:bg-slate-800"
        >
          <ChevronLeft className="w-4 h-4" />
          {copy.page} {Math.max(1, page - 1)}
        </button>

        <div className="flex flex-col items-center gap-1">
          <span className="text-xs font-semibold text-slate-500 tabular-nums">
            {copy.page} {page} {copy.of} {total}
          </span>
          <div className="h-1.5 w-32 rounded-full bg-slate-200 dark:bg-slate-800 overflow-hidden">
            <div className="h-full bg-emerald-500" style={{ width: `${percent}%` }} />
          </div>
        </div>

        <button
          type="button"
          onClick={() => goTo(page + 1)}
          disabled={page >= total}
          className="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-semibold rounded-lg border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 disabled:opacity-40 hover:bg-slate-100 dark:hover:bg-slate-800"
        >
          {copy.page} {Math.min(total, page + 1)}
          <ChevronRight className="w-4 h-4" />
        </button>
      </div>
    </div>
  );
}
