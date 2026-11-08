'use client';

import React, { useEffect, useState } from 'react';
import { BookOpen, Download, Heart, Lock, ShoppingCart, Star } from 'lucide-react';
import { LibraryReader } from './LibraryReader';
import { loadFavorites, loadProgress, toggleFavorite, type ReadingProgress } from '../lib/progress';
import type { AccessCapabilities } from '../lib/access';

interface ResourceActionsProps {
  slug: string;
  title: string;
  /** Pages de contenu lisible en ligne (vide si non disponible). */
  pages: string[];
  capabilities: AccessCapabilities;
  copy: {
    readOnline: string;
    download: string;
    downloadDisabled: string;
    buy: string;
    subscribe: string;
    privateAccess: string;
    addFavorite: string;
    removeFavorite: string;
    notAvailable: string;
    reader: {
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
  };
}

/**
 * Actions d'une ressource — lecture en ligne, téléchargement, achat, favori.
 *
 * Composant CLIENT : la lecture ouvre le lecteur intégré ; le favori et la
 * progression sont persistés en `localStorage` (visiteurs anonymes). Les
 * capacités d'accès sont calculées côté serveur et passées en props — le
 * bouton de téléchargement n'apparaît QUE si la licence et l'accès l'autorisent.
 */
export function ResourceActions({ slug, title, pages, capabilities, copy }: ResourceActionsProps) {
  const [reading, setReading] = useState(false);
  const [favorite, setFavorite] = useState(false);
  const [progress, setProgress] = useState<ReadingProgress | null>(null);

  useEffect(() => {
    setFavorite(loadFavorites().includes(slug));
    setProgress(loadProgress().find((item) => item.slug === slug) ?? null);
  }, [slug]);

  const canRead = capabilities.canRead && pages.length > 0;

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap gap-3">
        {canRead && (
          <button
            type="button"
            onClick={() => setReading((value) => !value)}
            className="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-semibold rounded-xl bg-gradient-to-r from-emerald-700 to-emerald-800 text-white hover:from-emerald-800 hover:to-emerald-900 transition-colors"
          >
            <BookOpen className="w-4 h-4" />
            {copy.readOnline}
          </button>
        )}

        {capabilities.canDownload ? (
          <a
            href={`/library/${slug}/download`}
            className="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-semibold rounded-xl border border-emerald-300 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300 hover:bg-emerald-50 dark:hover:bg-emerald-950/40 transition-colors"
          >
            <Download className="w-4 h-4" />
            {copy.download}
          </a>
        ) : (
          <span className="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-medium rounded-xl border border-slate-200 dark:border-slate-800 text-slate-400">
            <Lock className="w-4 h-4" />
            {copy.downloadDisabled}
          </span>
        )}

        {capabilities.requiresPurchase && (
          <span className="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-semibold rounded-xl bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300">
            <ShoppingCart className="w-4 h-4" />
            {copy.buy}
          </span>
        )}

        {capabilities.requiresSubscription && (
          <span className="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-semibold rounded-xl bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300">
            <Star className="w-4 h-4" />
            {copy.subscribe}
          </span>
        )}

        {capabilities.requiresPrivateAccess && (
          <span className="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-medium rounded-xl border border-slate-200 dark:border-slate-800 text-slate-500">
            <Lock className="w-4 h-4" />
            {copy.privateAccess}
          </span>
        )}

        <button
          type="button"
          onClick={() => setFavorite(toggleFavorite(slug).includes(slug))}
          aria-pressed={favorite}
          className="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-semibold rounded-xl border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
        >
          <Heart className={`w-4 h-4 ${favorite ? 'fill-rose-500 text-rose-500' : ''}`} />
          {favorite ? copy.removeFavorite : copy.addFavorite}
        </button>
      </div>

      {progress && canRead && (
        <p className="text-xs text-slate-500 dark:text-slate-400">
          {copy.reader.lastPosition} : {copy.reader.page} {progress.page}
        </p>
      )}

      {!canRead && pages.length === 0 && (
        <p className="text-sm text-slate-500 dark:text-slate-400">{copy.notAvailable}</p>
      )}

      {reading && canRead && (
        <LibraryReader
          slug={slug}
          title={title}
          pages={pages}
          initialProgress={progress}
          copy={copy.reader}
        />
      )}
    </div>
  );
}
