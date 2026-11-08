import React from 'react';
import Link from 'next/link';
import { headers } from 'next/headers';
import { cache } from 'react';
import { normalizeLocale } from '@/lib/locale-core';
import { getLibraryCopy } from '@/modules/library/data/copy';

/**
 * Layout de la catégorie autonome « Leopardo Library » (`/library`).
 *
 * Route PUBLIQUE : aucun gate de session (hors `PROTECTED_PREFIXES`), aucun
 * appel API. Le chrome est volontairement minimal et autonome (en-tête + pied
 * de page) pour que la bibliothèque reste indépendante de la vitrine : si la
 * vitrine évolue, la bibliothèque ne casse pas — et inversement.
 *
 * L'arabe est RTL : le sens d'écriture est appliqué au niveau du layout racine
 * à partir de la locale résolue ici (en-tête `x-vitrine-lang`).
 */

const getSsrLocale = cache(async () => {
  const headerList = await headers();
  const urlLang = headerList.get('x-vitrine-lang');
  if (urlLang) return normalizeLocale(urlLang);
  const base = (headerList.get('accept-language') ?? '').split(',')[0].trim().toLowerCase().slice(0, 2);
  return normalizeLocale(base);
});

export default async function LibraryLayout({ children }: { children: React.ReactNode }) {
  const locale = await getSsrLocale();
  const copy = getLibraryCopy(locale);

  return (
    <div className="min-h-screen bg-slate-50 dark:bg-slate-950">
      <header className="border-b border-slate-200 dark:border-slate-800 bg-white/80 dark:bg-slate-900/80 backdrop-blur">
        <div className="mx-auto flex max-w-5xl items-center justify-between px-4 py-4">
          <Link href="/library" className="flex items-center gap-2">
            <span className="text-lg font-bold text-slate-900 dark:text-white">
              {copy.home.title}
            </span>
          </Link>
          <nav className="flex items-center gap-4">
            <Link
              href="/library/account"
              className="text-sm font-medium text-slate-500 dark:text-slate-400 hover:text-emerald-700 dark:hover:text-emerald-300 transition-colors"
            >
              {copy.account.title}
            </Link>
            <Link
              href="/"
              className="text-sm font-medium text-slate-500 dark:text-slate-400 hover:text-emerald-700 dark:hover:text-emerald-300 transition-colors"
            >
              Leopardo
            </Link>
          </nav>
        </div>
      </header>

      <div className="mx-auto max-w-5xl px-4 py-8 sm:py-12">{children}</div>

      <footer className="border-t border-slate-200 dark:border-slate-800 mt-8">
        <div className="mx-auto max-w-5xl px-4 py-6">
          <p className="text-xs text-slate-500 dark:text-slate-400">{copy.common.disclaimer}</p>
        </div>
      </footer>
    </div>
  );
}
