import type { Metadata } from 'next';
import { notFound } from 'next/navigation';
import { headers } from 'next/headers';
import { cache } from 'react';
import { normalizeLocale } from '@/lib/locale-core';
import { SITE_URL } from '@/lib/site-url';
import { getToolboxCopy } from '@/modules/toolbox/data/copy';
import { getAllToolSlugs, getTool } from '@/modules/toolbox/lib/registry';
import { ToolShell } from '@/modules/toolbox/components/ToolShell';
import { ToolRenderer } from '@/modules/toolbox/components/ToolRenderer';
import { ToolActions } from '@/modules/toolbox/components/ToolActions';

/**
 * Page d'un outil public (`/toolbox/[slug]`).
 *
 * Page PUBLIQUE et INDEXABLE, générée statiquement (`generateStaticParams`) :
 * aucun compte requis, aucun appel API, calcul 100 % côté client. Un slug
 * inconnu renvoie un 404 propre (`notFound()`), sans casser le reste du site.
 */

const getSsrLocale = cache(async () => {
  const headerList = await headers();
  const urlLang = headerList.get('x-vitrine-lang');
  if (urlLang) return normalizeLocale(urlLang);
  const base = (headerList.get('accept-language') ?? '').split(',')[0].trim().toLowerCase().slice(0, 2);
  return normalizeLocale(base);
});

export function generateStaticParams() {
  return getAllToolSlugs().map((slug) => ({ slug }));
}

interface ToolPageProps {
  params: Promise<{ slug: string }>;
}

export async function generateMetadata({ params }: ToolPageProps): Promise<Metadata> {
  const { slug } = await params;
  const tool = getTool(slug);
  if (!tool) return { title: 'Toolbox' };

  const locale = await getSsrLocale();
  const copy = getToolboxCopy(locale);
  const toolCopy = copy.tools[slug];

  return {
    title: toolCopy?.name ?? slug,
    description: toolCopy?.description ?? '',
    keywords: tool.keywords,
    alternates: {
      canonical: `${SITE_URL}/toolbox/${slug}`,
      languages: {
        fr: `${SITE_URL}/toolbox/${slug}`,
        en: `${SITE_URL}/toolbox/${slug}?lang=en`,
        tr: `${SITE_URL}/toolbox/${slug}?lang=tr`,
        ar: `${SITE_URL}/toolbox/${slug}?lang=ar`,
        'x-default': `${SITE_URL}/toolbox/${slug}`,
      },
    },
    robots: { index: true, follow: true },
    openGraph: {
      title: toolCopy?.name ?? slug,
      description: toolCopy?.description ?? '',
      type: 'website',
      url: `${SITE_URL}/toolbox/${slug}`,
    },
  };
}

export default async function ToolPage({ params }: ToolPageProps) {
  const { slug } = await params;
  const tool = getTool(slug);
  if (!tool) notFound();

  const locale = await getSsrLocale();
  const copy = getToolboxCopy(locale);
  const toolCopy = copy.tools[slug];
  if (!toolCopy) notFound();

  const warnings = tool.warnings.map((id) => copy.warnings[id]).filter(Boolean);
  const references = tool.references.map((reference) => ({
    label: copy.refs[reference.labelKey] ?? reference.labelKey,
    url: reference.url,
  }));

  return (
    <ToolShell
      title={toolCopy.name}
      description={toolCopy.description}
      categoryLabel={copy.categories[tool.category] ?? tool.category}
      warnings={warnings}
      references={references}
      referencesLabel={copy.common.reference}
      versionLabel={copy.common.version}
      version={tool.version}
      backLabel={copy.common.backToCatalog}
      disclaimer={copy.common.disclaimer}
    >
      <ToolActions slug={slug} copy={copy.myTools} />
      <ToolRenderer slug={slug} copy={toolCopy} common={copy.common} locale={locale} />
    </ToolShell>
  );
}
