import { Metadata } from 'next';
import { headers } from 'next/headers';
import { SITE_URL } from '@/lib/site-url';
import { generateMetadata as generateSEOMetadata } from '@/modules/vitrine/lib/seo';
import type { AppLocale } from '@/lib/i18n';
import { BreadcrumbJsonLd } from '@/components/JsonLd';
import { breadcrumbLabels, localizedUrl } from '@/lib/ai-search';
import { alternativesHubSeo } from '@/modules/vitrine/data/alternatives';

/**
 * #7869 — Hub SEO « Alternatives & comparatifs » (/alternatives).
 * Metadata serveur dédiée + fil d'Ariane JSON-LD, pattern identique à
 * blog/layout.tsx (le rendu de la page reste client, children pass-through).
 */

async function resolveLocale(): Promise<AppLocale> {
  // #4004 : ?lang= normalisé en en-tête x-vitrine-lang par le middleware.
  const headerList = await headers();
  return (headerList.get('x-vitrine-lang') ?? 'fr') as AppLocale;
}

export async function generateMetadata(): Promise<Metadata> {
  const lang = await resolveLocale();
  const seo = alternativesHubSeo[lang] ?? alternativesHubSeo.fr;

  return generateSEOMetadata({
    title: seo.title,
    description: seo.description,
    ogType: 'website',
    canonical: `${SITE_URL}/alternatives`,
    locale: lang,
  });
}

export default async function AlternativesLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  const lang = await resolveLocale();
  const labels = breadcrumbLabels(lang);
  const seo = alternativesHubSeo[lang] ?? alternativesHubSeo.fr;

  return (
    <>
      <BreadcrumbJsonLd
        items={[
          { name: labels.home, url: localizedUrl('/', lang) },
          { name: seo.title, url: localizedUrl('/alternatives', lang) },
        ]}
      />
      {children}
    </>
  );
}
