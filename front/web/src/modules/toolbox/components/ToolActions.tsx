'use client';

import React, { useCallback, useEffect, useState } from 'react';
import { Star } from 'lucide-react';
import { isFavorite, recordUsage, toggleFavorite } from '../lib/storage';

interface ToolActionsProps {
  slug: string;
  copy: {
    addFavorite: string;
    removeFavorite: string;
  };
}

/**
 * Actions personnelles d'une page d'outil — favori + enregistrement d'usage.
 *
 * Composant CLIENT, 100 % local (`lib/storage.ts`, décision #9156) : aucun
 * compte, aucun appel réseau. L'état initial est `null` puis hydraté dans un
 * `useEffect` — le serveur ne peut pas lire `localStorage`, et rendre un état
 * différent au premier rendu client casserait l'hydratation.
 *
 * L'usage est enregistré **une fois par montage** : ouvrir un outil le place en
 * tête de l'historique, sans écrire à chaque frappe.
 */
export function ToolActions({ slug, copy }: ToolActionsProps) {
  const [favorite, setFavorite] = useState<boolean | null>(null);

  useEffect(() => {
    setFavorite(isFavorite(slug));
    recordUsage(slug);
  }, [slug]);

  const handleToggle = useCallback(() => {
    const next = toggleFavorite(slug);
    setFavorite(next.favorites.includes(slug));
  }, [slug]);

  // Avant hydratation : bouton inerte mais présent (pas de saut de mise en page).
  if (favorite === null) {
    return (
      <div className="mb-4">
        <span className="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-semibold rounded-lg border border-slate-200 dark:border-slate-800 text-slate-400 dark:text-slate-500">
          <Star className="w-4 h-4" />
          {copy.addFavorite}
        </span>
      </div>
    );
  }

  return (
    <div className="mb-4">
      <button
        type="button"
        onClick={handleToggle}
        aria-pressed={favorite}
        className={
          favorite
            ? 'inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-semibold rounded-lg border border-amber-300 dark:border-amber-800 bg-amber-50 dark:bg-amber-950/30 text-amber-800 dark:text-amber-200 hover:bg-amber-100 dark:hover:bg-amber-950/60 transition-colors'
            : 'inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-semibold rounded-lg border border-slate-300 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:border-amber-400 hover:text-amber-700 dark:hover:text-amber-300 transition-colors'
        }
      >
        <Star className={`w-4 h-4 ${favorite ? 'fill-current' : ''}`} />
        {favorite ? copy.removeFavorite : copy.addFavorite}
      </button>
    </div>
  );
}
