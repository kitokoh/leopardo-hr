'use client';

import React, { useCallback, useEffect, useState } from 'react';
import { Check, Link2 } from 'lucide-react';
import { buildShareUrl, type ToolStateValues } from '../lib/share';

interface ShareButtonProps {
  /** Valeurs courantes du formulaire (les ENTRÉES, jamais le résultat). */
  values: ToolStateValues;
  copy: {
    share: string;
    shared: string;
    shareFailed: string;
  };
}

/**
 * Bouton « Partager ce calcul » — copie un lien portant les entrées.
 *
 * Le lien est construit à partir de `window.location.href` (donc du domaine et
 * de la langue réels) et ne contient que les valeurs saisies : aucun compte,
 * aucun appel réseau, aucune donnée envoyée à un serveur.
 *
 * Repli quand `navigator.clipboard` est indisponible (contexte non sécurisé) :
 * on affiche l'URL dans un champ sélectionnable plutôt que d'échouer en silence.
 */
export function ShareButton({ values, copy }: ShareButtonProps) {
  const [status, setStatus] = useState<'idle' | 'shared' | 'failed'>('idle');
  const [fallbackUrl, setFallbackUrl] = useState<string | null>(null);

  // Le statut revient à l'état initial après 2 s (même contrat que ResultPanel).
  useEffect(() => {
    if (status === 'idle') return;
    const timer = window.setTimeout(() => setStatus('idle'), 2000);
    return () => window.clearTimeout(timer);
  }, [status]);

  const handleShare = useCallback(async () => {
    if (typeof window === 'undefined') return;

    const url = buildShareUrl(window.location.href, values);

    try {
      await navigator.clipboard.writeText(url);
      setFallbackUrl(null);
      setStatus('shared');
    } catch {
      // Presse-papiers refusé : on expose le lien au lieu d'échouer.
      setFallbackUrl(url);
      setStatus('failed');
    }
  }, [values]);

  return (
    <div className="mt-4">
      <button
        type="button"
        onClick={handleShare}
        className="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-semibold rounded-lg border border-slate-300 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:border-emerald-400 hover:text-emerald-700 dark:hover:text-emerald-300 transition-colors"
      >
        {status === 'shared' ? <Check className="w-4 h-4" /> : <Link2 className="w-4 h-4" />}
        {status === 'shared' ? copy.shared : copy.share}
      </button>

      {status === 'failed' && fallbackUrl && (
        <div className="mt-2">
          <p className="text-xs text-slate-500 dark:text-slate-400 mb-1">{copy.shareFailed}</p>
          <input
            type="text"
            readOnly
            value={fallbackUrl}
            onFocus={(event) => event.currentTarget.select()}
            aria-label={copy.share}
            className="w-full px-3 py-2 text-xs bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg text-slate-700 dark:text-slate-300"
          />
        </div>
      )}
    </div>
  );
}
