import type { Metadata } from 'next';
import { headers } from 'next/headers';
import { cache } from 'react';
import { normalizeLocale } from '@/lib/locale-core';
import { getLibraryCopy } from '@/modules/library/data/copy';
import { getPublishedResources } from '@/modules/library/lib/registry';
import { LibraryAccount } from '@/modules/library/components/LibraryAccount';

/**
 * Espace utilisateur de la bibliothèque (`/library/account`).
 *
 * Page PUBLIQUE (aucun gate de session) : les favoris et la progression sont
 * stockés localement pour les visiteurs anonymes. Aucune création de compte
 * n'est requise pour consulter les ressources publiques gratuites.
 */

const getSsrLocale = cache(async () => {
  const headerList = await headers();
  const urlLang = headerList.get('x-vitrine-lang');
  if (urlLang) return normalizeLocale(urlLang);
  const base = (headerList.get('accept-language') ?? '').split(',')[0].trim().toLowerCase().slice(0, 2);
  return normalizeLocale(base);
});

export async function generateMetadata(): Promise<Metadata> {
  const locale = await getSsrLocale();
  const copy = getLibraryCopy(locale);
  return {
    title: `${copy.account.title} — ${copy.home.title}`,
    robots: { index: false, follow: true },
  };
}

export default async function LibraryAccountPage() {
  const locale = await getSsrLocale();
  const copy = getLibraryCopy(locale);

  const titles: Record<string, string> = {};
  for (const resource of getPublishedResources()) {
    titles[resource.slug] = resource.slug;
  }

  return (
    <div>
      <h1 className="text-3xl font-bold text-slate-900 dark:text-white mb-8">{copy.account.title}</h1>
      <LibraryAccount
        titles={titles}
        copy={{
          title: copy.account.title,
          favorites: copy.account.favorites,
          recentReads: copy.account.recentReads,
          progress: copy.account.progress,
          empty: copy.account.empty,
          signInHint: copy.account.signInHint,
          page: copy.reader.page,
          of: copy.reader.of,
        }}
      />
    </div>
  );
}
