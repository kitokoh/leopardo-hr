'use client';

import { useEffect } from 'react';
import Link from 'next/link';
import { useVitrineLocale } from '@/modules/vitrine/lib/vitrine-locale';
import { t } from '@/lib/i18n/locale-catalog';

/**
 * Frontière d'erreur globale (App Router).
 *
 * Sans ce fichier, toute erreur de rendu côté client remontait à la frontière
 * d'erreur par défaut de Next.js (écran générique, sans reprise possible). Ici
 * l'utilisateur dispose d'un bouton « Réessayer » et l'erreur est journalisée.
 *
 * #8497 (I-10) — le corps était 100 % français en dur alors que le produit est
 * multilingue (fr/en/tr/ar) : un utilisateur en/tr/ar tombait sur une page
 * d'erreur illisible. Tout le texte visible vient désormais du catalogue
 * partagé (`vitrine.error.*`, ×4 locales, propagé par `shared/i18n/sync`),
 * résolu via `useVitrineLocale` — même mécanique que `not-found.tsx` (#7664).
 * La garde PA2-I18N-014 (`check-i18n-diff.js`) refuse toute chaîne en dur.
 */
export default function GlobalError({
  error,
  reset,
}: {
  error: Error & { digest?: string };
  reset: () => void;
}) {
  const { locale } = useVitrineLocale();

  useEffect(() => {
    console.error('[app] unhandled render error', error);
  }, [error]);

  return (
    <main className="relative flex min-h-screen items-center justify-center overflow-hidden bg-slate-50 px-6 py-16 dark:bg-slate-950">
      <div className="pointer-events-none absolute inset-0 -z-10 flex items-center justify-center">
        <div className="h-96 w-96 rounded-full bg-red-500/10 blur-3xl dark:bg-red-500/15" />
      </div>
      <div className="w-full max-w-lg rounded-3xl border border-slate-200/80 bg-white/80 p-8 text-center shadow-xl backdrop-blur-xl sm:p-10 dark:border-slate-800/80 dark:bg-slate-900/60 dark:shadow-2xl">
        <p className="text-sm font-semibold uppercase tracking-[0.2em] text-red-600 dark:text-red-400">
          {t(locale, 'vitrine.error.kicker')}
        </p>
        <h1 className="mt-4 text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl dark:text-white">
          {t(locale, 'vitrine.error.title')}
        </h1>
        <p className="mt-4 text-base leading-relaxed text-slate-600 dark:text-slate-400">
          {t(locale, 'vitrine.error.body')}
        </p>
        {error.digest ? (
          <p className="mt-3 text-xs text-slate-400">
            {t(locale, 'vitrine.error.reference')} : <code className="font-mono">{error.digest}</code>
          </p>
        ) : null}
        <div className="mt-8 flex flex-wrap items-center justify-center gap-3">
          <button
            type="button"
            onClick={reset}
            className="inline-flex items-center rounded-xl bg-brand-700 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2"
          >
            {t(locale, 'vitrine.error.retry')}
          </button>
          <Link
            href="/"
            className="inline-flex items-center rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-900"
          >
            {t(locale, 'vitrine.error.home')}
          </Link>
          <Link
            href="/contact"
            className="inline-flex items-center rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-900"
          >
            {t(locale, 'vitrine.error.support')}
          </Link>
        </div>
      </div>
    </main>
  );
}
