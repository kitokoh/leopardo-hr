'use client';

import React, { useEffect } from 'react';
import Link from 'next/link';
import { AlertTriangle } from 'lucide-react';
import { getToolboxCopy } from '@/modules/toolbox/data/copy';
import { useToolboxLocale } from '@/modules/toolbox/lib/use-toolbox-locale';

/**
 * Frontière d'erreur du segment `/toolbox`.
 *
 * Sans ce fichier, une erreur de rendu dans un outil remontait à
 * `app/error.tsx` — la frontière RACINE — qui remplace tout le chrome de la
 * page. La contrainte du module (« si la Toolbox est indisponible, la vitrine
 * et les autres verticales continuent de fonctionner ») n'était donc pas tenue
 * au niveau du segment : l'utilisateur perdait aussi le retour au catalogue.
 *
 * Ici l'erreur reste CONFINÉE à la Toolbox : le layout du segment (en-tête,
 * pied de page) est conservé, et l'utilisateur garde deux issues — réessayer
 * ou revenir au catalogue.
 *
 * Toute la copie vient de `data/copy.ts` (fr/en/tr/ar) : la garde
 * `check-i18n-diff.js` refuse les chaînes en dur.
 */
export default function ToolboxError({
  error,
  reset,
}: {
  error: Error & { digest?: string };
  reset: () => void;
}) {
  const locale = useToolboxLocale();
  const copy = getToolboxCopy(locale);

  useEffect(() => {
    console.error('[toolbox] unhandled render error', error);
  }, [error]);

  return (
    <div className="mx-auto max-w-xl py-16 text-center">
      <div className="mx-auto mb-5 flex h-12 w-12 items-center justify-center rounded-full bg-amber-50 dark:bg-amber-950/30">
        <AlertTriangle className="h-6 w-6 text-amber-600 dark:text-amber-400" />
      </div>
      <h1 className="mb-3 text-2xl font-bold text-slate-900 dark:text-white">
        {copy.common.errorTitle}
      </h1>
      <p className="mb-6 text-sm text-slate-600 dark:text-slate-400">{copy.common.errorBody}</p>
      {error.digest ? (
        <p className="mb-6 text-xs text-slate-400">
          <code className="font-mono">{error.digest}</code>
        </p>
      ) : null}
      <div className="flex flex-wrap items-center justify-center gap-3">
        <button
          type="button"
          onClick={reset}
          className="inline-flex items-center rounded-xl bg-emerald-700 px-5 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-emerald-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2"
        >
          {copy.common.errorRetry}
        </button>
        <Link
          href="/toolbox"
          className="inline-flex items-center rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-semibold text-slate-700 transition-colors hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-900"
        >
          {copy.common.backToCatalog}
        </Link>
      </div>
    </div>
  );
}
