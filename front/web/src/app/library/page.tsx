import type { Metadata } from 'next';
import Link from 'next/link';
import { headers } from 'next/headers';
import { cache } from 'react';
import { normalizeLocale } from '@/lib/locale-core';
import { SITE_URL } from '@/lib/site-url';
import { LIBRARY_CATEGORIES } from '@/modules/library/data/catalog';
import { getLibraryCopy } from '@/modules/library/data/copy';
import {
  getFeaturedResources,
  getFreeResources,
  getNewResources,
  getPublishedResources,
  getAvailableLanguages,
} from '@/modules/library/lib/registry';
import { toCatalogItem } from '@/modules/library/lib/present';
import { LibraryCatalog } from '@/modules/library/components/LibraryCatalog';
import { ResourceCard } from '@/modules/library/components/ResourceCard';

/**
 * Accueil public de la bibliothèque (`/library`).
 *
 * Page PUBLIQUE et INDEXABLE : aucun compte requis, aucun appel API. Les
 * métadonnées sont localisées (fr/en/tr/ar) et les alternates hreflang sont
 * émis comme sur les autres surfaces publiques (`/toolbox`, `/decouvrir`).
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
    title: copy.meta.title,
    description: copy.meta.description,
    alternates: {
      canonical: `${SITE_URL}/library`,
      languages: {
        fr: `${SITE_URL}/library`,
        en: `${SITE_URL}/library?lang=en`,
        tr: `${SITE_URL}/library?lang=tr`,
        ar: `${SITE_URL}/library?lang=ar`,
        'x-default': `${SITE_URL}/library`,
      },
    },
    robots: { index: true, follow: true },
    openGraph: {
      title: copy.meta.title,
      description: copy.meta.description,
      type: 'website',
      url: `${SITE_URL}/library`,
    },
  };
}

export default async function LibraryHomePage() {
  const locale = await getSsrLocale();
  const copy = getLibraryCopy(locale);

  const published = getPublishedResources();
  const items = published.map((resource) => toCatalogItem(resource, copy));
  const categories = LIBRARY_CATEGORIES.map((category) => ({
    id: category.id,
    label: copy.categories[category.id] ?? category.id,
  }));
  const languages = getAvailableLanguages().map((code) => ({ id: code, label: code.toUpperCase() }));
  const accessOptions = (['free', 'read_only', 'purchase', 'subscription', 'private'] as const).map((id) => ({
    id,
    label: copy.access[id],
  }));
  const typeOptions = (['book', 'ebook', 'guide', 'manual', 'report', 'article'] as const).map((id) => ({
    id,
    label: copy.types[id],
  }));

  const featured = getFeaturedResources();
  const newArrivals = getNewResources();
  const free = getFreeResources();

  const section = (
    title: string,
    resources: typeof published,
  ) =>
    resources.length > 0 && (
      <section className="mb-10">
        <h2 className="text-xl font-bold text-slate-900 dark:text-white mb-4">{title}</h2>
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {resources.slice(0, 6).map((resource) => (
            <ResourceCard
              key={resource.slug}
              href={`/library/${resource.slug}`}
              title={resource.slug}
              author={resource.author}
              categoryLabel={copy.categories[resource.category] ?? resource.category}
              accessLabel={copy.access[resource.accessModel]}
              accessTone={
                resource.accessModel === 'free' || resource.accessModel === 'read_only'
                  ? 'free'
                  : resource.accessModel === 'private'
                    ? 'private'
                    : 'paid'
              }
              openLabel={copy.detail.readOnline}
            />
          ))}
        </div>
      </section>
    );

  return (
    <div>
      <section className="mb-10">
        <h1 className="text-3xl sm:text-4xl font-bold text-slate-900 dark:text-white mb-3">
          {copy.home.title}
        </h1>
        <p className="text-base text-slate-600 dark:text-slate-400 mb-6 max-w-2xl">
          {copy.home.subtitle}
        </p>
        <p className="text-sm font-semibold text-emerald-700 dark:text-emerald-300">
          {published.length} {copy.home.resourcesCount}
        </p>
      </section>

      {section(copy.home.editorial, featured)}
      {section(copy.home.newArrivals, newArrivals)}
      {section(copy.home.freeResources, free)}

      <section className="mb-10">
        <h2 className="text-xl font-bold text-slate-900 dark:text-white mb-4">{copy.home.categories}</h2>
        <div className="flex flex-wrap gap-2">
          {categories.map((category) => (
            <Link
              key={category.id}
              href={`/library?category=${category.id}`}
              className="inline-flex px-3.5 py-2 text-sm font-medium rounded-full border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 hover:border-emerald-400 transition-colors"
            >
              {category.label}
            </Link>
          ))}
        </div>
      </section>

      <section>
        <h2 className="text-xl font-bold text-slate-900 dark:text-white mb-4">{copy.home.browseAll}</h2>
        <LibraryCatalog
          items={items}
          categories={categories}
          languages={languages}
          accessOptions={accessOptions}
          typeOptions={typeOptions}
          copy={{
            searchPlaceholder: copy.home.searchPlaceholder,
            noResults: copy.home.noResults,
            allCategories: copy.filters.allCategories,
            allLanguages: copy.filters.allLanguages,
            allAccess: copy.filters.allAccess,
            allTypes: copy.filters.allTypes,
            freeOnly: copy.filters.freeOnly,
            openLabel: copy.detail.readOnline,
            resourcesCount: copy.home.resourcesCount,
          }}
        />
      </section>
    </div>
  );
}
