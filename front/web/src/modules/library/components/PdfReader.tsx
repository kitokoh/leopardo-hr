'use client';

import React, { useCallback, useEffect, useRef, useState } from 'react';
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
import { clampProgress, progressPercent, saveProgress, type ReadingProgress } from '../lib/progress';

interface PdfReaderProps {
  slug: string;
  title: string;
  /** URL du PDF (présignée ou route interne signée). */
  fileUrl: string;
  initialProgress?: ReadingProgress | null;
  copy: {
    page: string;
    of: string;
    zoomIn: string;
    zoomOut: string;
    fullscreen: string;
    exitFullscreen: string;
    search: string;
    searchPlaceholder: string;
    noMatches: string;
    lastPosition: string;
    close: string;
    loading: string;
    error: string;
  };
}

/**
 * Lecteur PDF (pdf.js) — navigation, zoom, plein écran, recherche dans le
 * document, reprise de lecture.
 *
 * ⚠️ pdf.js est chargé DYNAMIQUEMENT (`import()` dans un effet) : il n'entre
 * pas dans le bundle initial de la vitrine, et le worker est résolu via
 * `new URL(..., import.meta.url)` (Next/Turbopack le sert en asset statique).
 *
 * Le fichier n'est jamais téléchargé en entier par l'interface : pdf.js fait
 * des requêtes HTTP Range sur l'URL fournie (présignée ou signée côté serveur).
 */
export function PdfReader({ slug, title, fileUrl, initialProgress, copy }: PdfReaderProps) {
  const canvasRef = useRef<HTMLCanvasElement>(null);
  const containerRef = useRef<HTMLDivElement>(null);
  const docRef = useRef<any>(null);

  const [total, setTotal] = useState(0);
  const [page, setPage] = useState(() => (initialProgress ? Math.max(1, initialProgress.page) : 1));
  const [zoom, setZoom] = useState(1.2);
  const [fullscreen, setFullscreen] = useState(false);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(false);
  const [searchOpen, setSearchOpen] = useState(false);
  const [query, setQuery] = useState('');
  const [matches, setMatches] = useState<number[]>([]);
  const [searching, setSearching] = useState(false);

  // Chargement du document.
  useEffect(() => {
    let cancelled = false;

    (async () => {
      try {
        const pdfjs = await import('pdfjs-dist');
        pdfjs.GlobalWorkerOptions.workerSrc = new URL(
          'pdfjs-dist/build/pdf.worker.min.mjs',
          import.meta.url,
        ).toString();

        const doc = await pdfjs.getDocument({ url: fileUrl }).promise;
        if (cancelled) return;

        docRef.current = doc;
        setTotal(doc.numPages);
        setLoading(false);
      } catch {
        if (!cancelled) {
          setError(true);
          setLoading(false);
        }
      }
    })();

    return () => {
      cancelled = true;
      docRef.current?.destroy?.();
      docRef.current = null;
    };
  }, [fileUrl]);

  // Rendu de la page courante.
  useEffect(() => {
    const doc = docRef.current;
    const canvas = canvasRef.current;
    if (!doc || !canvas || total === 0) return;

    let cancelled = false;

    (async () => {
      try {
        const pdfPage = await doc.getPage(page);
        if (cancelled) return;

        const viewport = pdfPage.getViewport({ scale: zoom });
        const context = canvas.getContext('2d');
        if (!context) return;

        canvas.width = viewport.width;
        canvas.height = viewport.height;
        await pdfPage.render({ canvasContext: context, viewport, canvas }).promise;
      } catch {
        if (!cancelled) setError(true);
      }
    })();

    return () => {
      cancelled = true;
    };
  }, [page, zoom, total]);

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
    if (total > 0) {
      saveProgress({ slug, page, totalPages: total, updatedAt: Date.now() });
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [total]);

  // Recherche dans le document (extraction texte page par page).
  const runSearch = useCallback(async () => {
    const doc = docRef.current;
    const needle = query.trim().toLowerCase();
    if (!doc || needle === '') {
      setMatches([]);
      return;
    }

    setSearching(true);
    const found: number[] = [];

    try {
      for (let index = 1; index <= total; index += 1) {
        const pdfPage = await doc.getPage(index);
        const content = await pdfPage.getTextContent();
        const text = content.items
          .map((item: any) => (typeof item.str === 'string' ? item.str : ''))
          .join(' ')
          .toLowerCase();
        if (text.includes(needle)) found.push(index);
      }
    } catch {
      // Recherche best-effort : un échec d'extraction ne casse pas la lecture.
    }

    setMatches(found);
    setSearching(false);
  }, [query, total]);

  const percent = progressPercent(page, total);

  return (
    <div
      ref={containerRef}
      className={
        fullscreen
          ? 'fixed inset-0 z-50 bg-slate-100 dark:bg-slate-950 flex flex-col'
          : 'rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-100 dark:bg-slate-900 flex flex-col'
      }
    >
      {/* Barre d'outils */}
      <div className="flex flex-wrap items-center gap-2 border-b border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-4 py-3">
        <span className="text-sm font-bold text-slate-900 dark:text-white mr-auto truncate">{title}</span>

        <button
          type="button"
          onClick={() => setZoom((z) => Math.max(0.5, +(z - 0.2).toFixed(2)))}
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
          onClick={() => setZoom((z) => Math.min(3, +(z + 0.2).toFixed(2)))}
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

      {/* Recherche */}
      {searchOpen && (
        <div className="border-b border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-4 py-3">
          <div className="flex items-center gap-2">
            <input
              type="search"
              value={query}
              onChange={(event) => setQuery(event.target.value)}
              onKeyDown={(event) => {
                if (event.key === 'Enter') void runSearch();
              }}
              placeholder={copy.searchPlaceholder}
              aria-label={copy.search}
              className="flex-1 px-3 py-2 text-sm bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500"
            />
            <button
              type="button"
              onClick={() => void runSearch()}
              disabled={searching}
              className="px-3 py-2 text-sm font-semibold rounded-lg bg-emerald-700 text-white disabled:opacity-50"
            >
              {searching ? <Loader2 className="w-4 h-4 animate-spin" /> : copy.search}
            </button>
            <button
              type="button"
              onClick={() => {
                setSearchOpen(false);
                setQuery('');
                setMatches([]);
              }}
              aria-label={copy.close}
              className="p-2 rounded-lg text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800"
            >
              <X className="w-4 h-4" />
            </button>
          </div>
          {query.trim() !== '' && !searching && (
            <div className="mt-2 text-xs text-slate-500 dark:text-slate-400">
              {matches.length === 0 ? (
                <span>{copy.noMatches}</span>
              ) : (
                <ul className="flex flex-wrap gap-2">
                  {matches.map((match) => (
                    <li key={match}>
                      <button
                        type="button"
                        onClick={() => goTo(match)}
                        className="px-2 py-1 rounded-md bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 font-semibold"
                      >
                        {copy.page} {match}
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
      <div className="flex-1 overflow-auto flex items-start justify-center p-4">
        {loading && (
          <div className="flex items-center gap-2 text-sm text-slate-500 py-16">
            <Loader2 className="w-4 h-4 animate-spin" />
            {copy.loading}
          </div>
        )}
        {error && !loading && (
          <p className="text-sm text-rose-600 dark:text-rose-400 py-16">{copy.error}</p>
        )}
        <canvas ref={canvasRef} className={loading || error ? 'hidden' : 'shadow-lg rounded bg-white'} />
      </div>

      {/* Navigation */}
      <div className="flex items-center justify-between gap-3 border-t border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-4 py-3">
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
            {copy.page} {page} {copy.of} {total || '—'}
          </span>
          <div className="h-1.5 w-32 rounded-full bg-slate-200 dark:bg-slate-800 overflow-hidden">
            <div className="h-full bg-emerald-500" style={{ width: `${percent}%` }} />
          </div>
        </div>

        <button
          type="button"
          onClick={() => goTo(page + 1)}
          disabled={total === 0 || page >= total}
          className="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-semibold rounded-lg border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 disabled:opacity-40 hover:bg-slate-100 dark:hover:bg-slate-800"
        >
          {copy.page} {Math.min(total || 1, page + 1)}
          <ChevronRight className="w-4 h-4" />
        </button>
      </div>
    </div>
  );
}
