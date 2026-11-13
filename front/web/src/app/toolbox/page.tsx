import type { Metadata } from 'next';
import { headers } from 'next/headers';
import { cache } from 'react';
import { normalizeLocale } from '@/lib/locale-core';
import { SITE_URL } from '@/lib/site-url';
import { TOOL_CATEGORIES } from '@/modules/toolbox/data/catalog';
import { getCatalogTools } from '@/modules/toolbox/lib/registry';
import { getToolboxCopy } from '@/modules/toolbox/data/copy';
import { ToolboxCatalog, type CatalogItem } from '@/modules/toolbox/components/ToolboxCatalog';
import { MyTools, type MyToolsItem } from '@/modules/toolbox/components/MyTools';

/**
 * Catalogue public de la Toolbox (`/toolbox`).
 *
 * Page PUBLIQUE et INDEXABLE : aucun compte requis, aucun appel API. Les
 * métadonnées sont localisées (fr/en/tr/ar) et les alternates hreflang sont
 * émis comme sur les autres surfaces publiques (`/decouvrir`, `/restaurants`).
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
  const copy = getToolboxCopy(locale);

  return {
    title: copy.meta.title,
    description: copy.meta.description,
    alternates: {
      canonical: `${SITE_URL}/toolbox`,
      languages: {
        fr: `${SITE_URL}/toolbox`,
        en: `${SITE_URL}/toolbox?lang=en`,
        tr: `${SITE_URL}/toolbox?lang=tr`,
        ar: `${SITE_URL}/toolbox?lang=ar`,
        'x-default': `${SITE_URL}/toolbox`,
      },
    },
    robots: { index: true, follow: true },
    openGraph: {
      title: copy.meta.title,
      description: copy.meta.description,
      type: 'website',
      url: `${SITE_URL}/toolbox`,
    },
  };
}

export default async function ToolboxPage() {
  const locale = await getSsrLocale();
  const copy = getToolboxCopy(locale);

  const items: CatalogItem[] = getCatalogTools().map((tool) => {
    const toolCopy = copy.tools[tool.slug];
    return {
      slug: tool.slug,
      name: toolCopy?.name ?? tool.slug,
      description: toolCopy?.description ?? '',
      category: tool.category,
      categoryLabel: copy.categories[tool.category] ?? tool.category,
      keywords: tool.keywords,
    };
  });

  const categories = TOOL_CATEGORIES.map((category) => ({
    id: category.id,
    label: copy.categories[category.id] ?? category.id,
  }));

  // « Mes outils » ne reçoit que des données sérialisables : le composant
  // client lit `localStorage` lui-même (décision #9156, aucun appel réseau).
  const myToolsItems: MyToolsItem[] = items.map((item) => ({
    slug: item.slug,
    name: item.name,
    categoryLabel: item.categoryLabel,
  }));

  return (
    <div>
      <h1 className="text-3xl sm:text-4xl font-bold text-slate-900 dark:text-white mb-3">
        {copy.catalog.title}
      </h1>
      <p className="text-base text-slate-600 dark:text-slate-400 mb-8 max-w-2xl">
        {copy.catalog.subtitle}
      </p>

      <MyTools items={myToolsItems} copy={copy.myTools} />

      <ToolboxCatalog
        items={items}
        categories={categories}
        copy={{
          searchPlaceholder: copy.catalog.searchPlaceholder,
          noResults: copy.catalog.noResults,
          allCategories: copy.catalog.allCategories,
          openTool: copy.catalog.openTool,
          toolsCount: copy.catalog.toolsCount,
        }}
      />
    </div>
  );
}
