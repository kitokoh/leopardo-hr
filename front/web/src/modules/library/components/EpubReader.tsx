'use client';

import React, { useCallback, useEffect, useRef, useState } from 'react';
import { ChevronLeft, ChevronRight, Loader2, Maximize2, Minimize2, X } from 'lucide-react';
import { clampProgress, progressPercent, saveProgress, type ReadingProgress } from '../lib/progress';

interface EpubReaderProps {
  slug: string;
  title: string;
  /** URL de l'EPUB (présignée ou route interne signée). */
  fileUrl: string;
  initialProgress?: ReadingProgress | null;
  copy: {
    page: string;
    of: string;
    fullscreen: string;
    exitFullscreen: string;
    lastPosition: string;
    close: string;
    loading: string;
    error: string;
    prev: string;
    next: string;
  };
}

/**
 * Lecteur EPUB (epub.js) — navigation par chapitre, plein écran, reprise de
 * lecture (#9239).
 *
 * ⚠️ epub.js est chargé DYNAMIQUEMENT (`import()` dans un effet) : il n'entre
 * pas dans le bundle initial de la vitrine. Même stratégie que `PdfReader` pour
 * pdf.js — un lecteur n'est téléchargé que si l'utilisateur ouvre un EPUB.
 *
 * Le fichier n'est jamais exposé par une URL publique permanente : l'URL reçue
 * est présignée (stockage objet) ou signée HMAC (disque local), avec une durée
 * de vie courte.
 *
 * La progression est exprimée en PAGES de la table des matières (spine), pas en
 * pourcentage de défilement : c'est la seule unité stable d'un appareil à
 * l'autre, et elle se projette directement sur `ReadingProgress`.
 */
export function EpubReader({ slug, title, fileUrl, initialProgress, copy }: EpubReaderProps) {
  const viewerRef = useRef<HTMLDivElement>(null);
  const bookRef = useRef<any>(null);
  const renditionRef = useRef<any>(null);

  const [total, setTotal] = useState(0);
  const [page, setPage] = useState(() => (initialProgress ? Math.max(1, initialProgress.page) : 1));
  const [fullscreen, setFullscreen] = useState(false);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(false);

  // Chargement du livre.
  useEffect(() => {
    let cancelled = false;

    async function load() {
      try {
        const mod = await import('epubjs');
        const ePub = (mod as any).default ?? mod;
        if (cancelled || !viewerRef.current) return;

        const book = ePub(fileUrl);
        bookRef.current = book;

        const rendition = book.renderTo(viewerRef.current, {
          width: '100%',
          height: '100%',
          spread: 'none',
        });
        renditionRef.current = rendition;

        await rendition.display();

        // La table des matières (spine) donne le nombre de sections navigables.
        const navigation = await book.loaded.navigation;
        const spineItems = book.spine?.spineItems ?? [];
        const sections = spineItems.length || navigation?.toc?.length || 0;

        if (cancelled) return;

        setTotal(sections);
        setLoading(false);

        // Reprise de lecture : on affiche la section enregistrée.
        if (initialProgress && initialProgress.page > 1 && sections > 0) {
          const target = spineItems[clampProgress(initialProgress.page, sections) - 1];
          if (target?.href) {
            await rendition.display(target.href);
          }
        }

        // Chaque changement de section met à jour la position.
        rendition.on('relocated', (location: any) => {
          const index = location?.start?.index;
          if (typeof index === 'number') {
            setPage(index + 1);
          }
        });
      } catch {
        if (!cancelled) {
          setError(true);
          setLoading(false);
        }
      }
    }

    void load();

    return () => {
      cancelled = true;
      try {
        renditionRef.current?.destroy();
        bookRef.current?.destroy();
      } catch {
        // Déjà détruit : rien à faire.
      }
    };
    // `initialProgress` est volontairement hors dépendances : la reprise ne doit
    // se faire qu'au montage, pas à chaque mise à jour de la progression.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [fileUrl]);

  // Enregistrement de la position à chaque changement de section.
  useEffect(() => {
    if (total <= 0) return;
    saveProgress({ slug, page, totalPages: total, updatedAt: Date.now() });
  }, [slug, page, total]);

  const goTo = useCallback(
    (next: number) => {
      if (total <= 0) return;
      const clamped = clampProgress(next, total);
      const spineItems = bookRef.current?.spine?.spineItems ?? [];
      const target = spineItems[clamped - 1];
      if (target?.href) {
        void renditionRef.current?.display(target.href);
      }
      setPage(clamped);
    },
    [total],
  );

  const toggleFullscreen = useCallback(() => {
    const node = viewerRef.current?.parentElement;
    if (!node) return;

    if (!document.fullscreenElement) {
      void node.requestFullscreen?.();
      setFullscreen(true);
    } else {
      void document.exitFullscreen?.();
      setFullscreen(false);
    }
  }, []);

  return (
    <div className="flex h-full flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-950">
      {/* Barre d'outils */}
      <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-4 py-3 dark:border-slate-800">
        <div className="min-w-0">
          <p className="truncate text-sm font-semibold text-slate-900 dark:text-white">{title}</p>
          {total > 0 && (
            <p className="text-xs text-slate-500 dark:text-slate-400">
              {copy.page} {page} {copy.of} {total} · {progressPercent(page, total)}%
            </p>
          )}
        </div>

        <div className="flex items-center gap-1">
          <button
            type="button"
            onClick={() => goTo(page - 1)}
            disabled={page <= 1 || total <= 0}
            aria-label={copy.prev}
            className="rounded-lg p-2 text-slate-600 transition hover:bg-slate-100 disabled:opacity-40 dark:text-slate-300 dark:hover:bg-slate-800"
          >
            <ChevronLeft className="h-4 w-4" aria-hidden="true" />
          </button>
          <button
            type="button"
            onClick={() => goTo(page + 1)}
            disabled={total <= 0 || page >= total}
            aria-label={copy.next}
            className="rounded-lg p-2 text-slate-600 transition hover:bg-slate-100 disabled:opacity-40 dark:text-slate-300 dark:hover:bg-slate-800"
          >
            <ChevronRight className="h-4 w-4" aria-hidden="true" />
          </button>
          <button
            type="button"
            onClick={toggleFullscreen}
            aria-label={fullscreen ? copy.exitFullscreen : copy.fullscreen}
            className="rounded-lg p-2 text-slate-600 transition hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800"
          >
            {fullscreen ? (
              <Minimize2 className="h-4 w-4" aria-hidden="true" />
            ) : (
              <Maximize2 className="h-4 w-4" aria-hidden="true" />
            )}
          </button>
        </div>
      </div>

      {/* Zone de lecture */}
      <div className="relative min-h-[60vh] flex-1">
        {loading && (
          <div className="absolute inset-0 flex items-center justify-center gap-2 text-sm text-slate-500">
            <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" />
            {copy.loading}
          </div>
        )}

        {error && (
          <div className="absolute inset-0 flex flex-col items-center justify-center gap-2 p-6 text-center text-sm text-slate-500">
            <X className="h-5 w-5 text-rose-500" aria-hidden="true" />
            {copy.error}
          </div>
        )}

        <div ref={viewerRef} className="h-full w-full" aria-label={title} />
      </div>

      {initialProgress && initialProgress.page > 1 && (
        <p className="border-t border-slate-200 px-4 py-2 text-xs text-slate-500 dark:border-slate-800 dark:text-slate-400">
          {copy.lastPosition} : {copy.page} {initialProgress.page}
        </p>
      )}
    </div>
  );
}
