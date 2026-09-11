'use client';

import { useEffect } from 'react';
import Link from 'next/link';

/**
 * Frontière d'erreur globale (App Router).
 *
 * Sans ce fichier, toute erreur de rendu côté client remontait à la frontière
 * d'erreur par défaut de Next.js (écran générique, sans reprise possible). Ici
 * l'utilisateur dispose d'un bouton « Réessayer » et l'erreur est journalisée.
 */
export default function GlobalError({
  error,
  reset,
}: {
  error: Error & { digest?: string };
  reset: () => void;
}) {
  useEffect(() => {
    console.error('[app] unhandled render error', error);
  }, [error]);

  return (
    <main className="flex min-h-screen items-center justify-center bg-slate-50 px-6 py-16 dark:bg-slate-950">
      <div className="w-full max-w-lg text-center">
        <p className="text-sm font-semibold uppercase tracking-[0.2em] text-red-600 dark:text-red-400">
          Erreur inattendue
        </p>
        <h1 className="mt-4 text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl dark:text-white">
          Quelque chose s&apos;est mal passé
        </h1>
        <p className="mt-4 text-base leading-relaxed text-slate-600 dark:text-slate-400">
          L&apos;action n&apos;a pas pu aboutir. Vous pouvez réessayer ; si le problème
          persiste, contactez le support.
        </p>
        {error.digest ? (
          <p className="mt-3 text-xs text-slate-400">
            Référence technique : <code className="font-mono">{error.digest}</code>
          </p>
        ) : null}
        <div className="mt-8 flex flex-wrap items-center justify-center gap-3">
          <button
            type="button"
            onClick={reset}
            className="inline-flex items-center rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2"
          >
            Réessayer
          </button>
          <Link
            href="/"
            className="inline-flex items-center rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-900"
          >
            Retour à l&apos;accueil
          </Link>
        </div>
      </div>
    </main>
  );
}
