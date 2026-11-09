'use client';

import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
  ChevronLeft,
  ChevronRight,
  Loader2,
  Maximize2,
  Minimize2,
  Search,
  X,
  ZoomIn,
  ZoomOut,
} from 'lucide-react';
import { clampProgress, progressPercent } from '../lib/progress';
import { persistProgress } from '../lib/progress-sync';
import { parseEpub, type EpubChapter } from '../lib/epub';
import type { DocumentReaderProps } from '../lib/reader-contract';

/**
 * Lecteur EPUB — même contrat que `PdfReader` (`DocumentReaderProps`).
 *
 * Le fichier est téléchargé une fois, dézippé et converti en chapitres TEXTE
 * par `lib/epub.ts` (cœur pur, testé). Le rendu est donc du texte : aucun
 * XHTML d'un EPUB tiers n'est injecté dans le DOM, donc aucun script hostile
 * ne peut s'exécuter dans la vitrine.
 *
 * ⚠️ `fflate` est chargé DYNAMIQUEMENT (`import()` dans `lib/epub.ts` est
 * statique, mais ce composant n'est monté qu'à l'ouverture du lecteur) : le
 * dézippeur n'entre pas dans le bundle initial de la vitrine.
 *
 * En cas d'échec (archive corrompue, EPUB chiffré, réseau), on appelle
 * `onFallback` si fourni — l'appelant bascule alors sur le lecteur texte.
 */
export function EpubReader({
  slug,
  title,
  fileUrl,
  initialProgress,
  copy,
  onFallback,
}: DocumentReaderProps) {
  const containerRef = useRef<HTMLDivElement>(null);

  const [chapters, setChapters] = useState<EpubChapter[]>([]);
  const [bookTitle, setBookTitle] = useState<string | null>(null);
  const [index, setIndex] = useState(() =>
    initialProgress ? Math.max(0, initialProgress.page - 1) : 0,
  );
  const [zoom, setZoom] = useState(1);
  const [fullscreen, setFullscreen] = useState(false);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(false);
  const [searchOpen, setSearchOpen] = useState(false);
  const [query, setQuery] = useState('');

  // Chargement + extraction du contenu.
  useEffect(() => {
    let cancelled = false;

    (async () => {
      try {
        const response = await fetch(fileUrl, { credentials: 'same-origin' });
        if (!response.ok) throw new Error(`epub_http_${response.status}`);

        const buffer = await response.arrayBuffer();
        if (cancelled) return;

        const content = parseEpub(new Uint8Array(buffer));
        if (cancelled) return;

        setChapters(content.chapters);
        setBookTitle(content.title);
        setLoading(false);
      } catch {
        if (cancelled) return;
        setError(true);
        setLoading(false);
        onFallback?.();
      }
    })();

    return () => {
      cancelled = true;
    };
  }, [fileUrl, onFallback]);

  const total = chapters.length;

  const goTo = useCallback(
    (next: number) => {
      const clamped = clampProgress(next + 1, total) - 1;
      setIndex(clamped);
      persistProgress({ slug, page: clamped + 1, totalPages: total, updatedAt: Date.now() });
    },
    [slug, total],
  );

  // Enregistre la position à l'ouverture (reprise de lecture).
  useEffect(() => {
    if (total > 0) {
      persistProgress({ slug, page: index + 1, totalPages: total, updatedAt: Date.now() });
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [total]);

  const matches = useMemo(() => {
    const needle = query.trim().toLowerCase();
    if (needle === '') return [];
    return chapters
      .map((chapter, position) => ({ position, chapter }))
      .filter(
        (entry) =>
          entry.chapter.text.toLowerCase().includes(needle) ||
          entry.chapter.title.toLowerCase().includes(needle),
      );
  }, [chapters, query]);

  const current = chapters[index];
  const percent = progressPercent(index + 1, total);

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
        <span className="text-sm font-bold text-slate-900 dark:text-white mr-auto truncate">
          {bookTitle ?? title}
        </span>

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

      {/* Recherche dans le livre */}
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
                    <li key={match.position}>
                      <button
                        type="button"
                        onClick={() => goTo(match.position)}
                        className="px-2 py-1 rounded-md bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 font-semibold"
                      >
                        {copy.page} {match.position + 1}
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
        {loading && (
          <div className="flex items-center gap-2 text-sm text-slate-500 py-16 justify-center">
            <Loader2 className="w-4 h-4 animate-spin" />
            {copy.loading}
          </div>
        )}

        {error && !loading && (
          <p className="text-sm text-rose-600 dark:text-rose-400 py-16 text-center">{copy.error}</p>
        )}

        {!loading && !error && current && (
          <article className="mx-auto max-w-2xl">
            <h2 className="text-lg font-bold text-slate-900 dark:text-white mb-4">{current.title}</h2>
            <div
              className="whitespace-pre-line text-slate-800 dark:text-slate-200 leading-relaxed"
              style={{ fontSize: `${zoom}rem` }}
            >
              {current.text}
            </div>
          </article>
        )}
      </div>

      {/* Navigation */}
      <div className="flex items-center justify-between gap-3 border-t border-slate-200 dark:border-slate-800 px-4 py-3">
        <button
          type="button"
          onClick={() => goTo(index - 1)}
          disabled={index <= 0}
          className="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-semibold rounded-lg border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 disabled:opacity-40 hover:bg-slate-100 dark:hover:bg-slate-800"
        >
          <ChevronLeft className="w-4 h-4" />
          {copy.page} {Math.max(1, index)}
        </button>

        <div className="flex flex-col items-center gap-1">
          <span className="text-xs font-semibold text-slate-500 tabular-nums">
            {copy.page} {total === 0 ? '—' : index + 1} {copy.of} {total || '—'}
          </span>
          <div className="h-1.5 w-32 rounded-full bg-slate-200 dark:bg-slate-800 overflow-hidden">
            <div className="h-full bg-emerald-500" style={{ width: `${percent}%` }} />
          </div>
        </div>

        <button
          type="button"
          onClick={() => goTo(index + 1)}
          disabled={total === 0 || index >= total - 1}
          className="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-semibold rounded-lg border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 disabled:opacity-40 hover:bg-slate-100 dark:hover:bg-slate-800"
        >
          {copy.page} {Math.min(total || 1, index + 2)}
          <ChevronRight className="w-4 h-4" />
        </button>
      </div>
    </div>
  );
}
