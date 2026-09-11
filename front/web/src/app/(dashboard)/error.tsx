'use client';

import { useEffect } from 'react';
import Link from 'next/link';

/**
 * Frontière d'erreur du tableau de bord.
 *
 * Isole les erreurs des écrans métier du reste du site : une page en échec
 * n'emporte plus toute la coquille, et l'utilisateur peut relancer le rendu
 * sans recharger ni perdre sa session.
 */
export default function DashboardError({
  error,
  reset,
}: {
  error: Error & { digest?: string };
  reset: () => void;
}) {
  useEffect(() => {
    console.error('[dashboard] render error', error);
  }, [error]);

  return (
    <div className="flex min-h-[60vh] items-center justify-center px-6 py-16">
      <div className="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-8 text-center shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <h1 className="text-lg font-semibold text-slate-900 dark:text-white">
          Cet écran n&apos;a pas pu s&apos;afficher
        </h1>
        <p className="mt-3 text-sm leading-relaxed text-slate-600 dark:text-slate-400">
          La page a rencontré une erreur. Vos données ne sont pas affectées :
          réessayez, ou revenez au tableau de bord.
        </p>
        {error.digest ? (
          <p className="mt-3 text-xs text-slate-400">
            Référence technique : <code className="font-mono">{error.digest}</code>
          </p>
        ) : null}
        <div className="mt-6 flex flex-wrap items-center justify-center gap-3">
          <button
            type="button"
            onClick={reset}
            className="inline-flex items-center rounded-xl bg-brand-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2"
          >
            Réessayer
          </button>
          <Link
            href="/dashboard"
            className="inline-flex items-center rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
          >
            Tableau de bord
          </Link>
        </div>
      </div>
    </div>
  );
}
