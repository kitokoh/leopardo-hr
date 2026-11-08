import Link from 'next/link';
import { headers } from 'next/headers';
import { cache } from 'react';
import { normalizeLocale } from '@/lib/locale-core';
import { getLibraryCopy } from '@/modules/library/data/copy';

/**
 * 404 de la bibliothèque — un slug inconnu n'affecte que la bibliothèque : la
 * vitrine et les autres verticales continuent de fonctionner normalement.
 */

const getSsrLocale = cache(async () => {
  const headerList = await headers();
  const urlLang = headerList.get('x-vitrine-lang');
  if (urlLang) return normalizeLocale(urlLang);
  const base = (headerList.get('accept-language') ?? '').split(',')[0].trim().toLowerCase().slice(0, 2);
  return normalizeLocale(base);
});

export default async function LibraryNotFound() {
  const locale = await getSsrLocale();
  const copy = getLibraryCopy(locale);

  return (
    <div className="mx-auto max-w-xl py-16 text-center">
      <h1 className="text-2xl font-bold text-slate-900 dark:text-white mb-3">{copy.common.notFound}</h1>
      <p className="text-sm text-slate-600 dark:text-slate-400 mb-6">{copy.common.notFoundHint}</p>
      <Link
        href="/library"
        className="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-semibold rounded-xl bg-gradient-to-r from-emerald-700 to-emerald-800 text-white hover:from-emerald-800 hover:to-emerald-900 transition-colors"
      >
        {copy.common.backToLibrary}
      </Link>
    </div>
  );
}
