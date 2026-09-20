import { Metadata } from 'next';
import { headers } from 'next/headers';
import { notFound } from 'next/navigation';
import { SITE_URL } from '@/lib/site-url';
import {
  generateMetadata as generateSEOMetadata,
  generateFAQSchema,
} from '@/modules/vitrine/lib/seo';
import {
  getAlternativePage,
  alternativesHubLabel,
} from '@/modules/vitrine/data/alternatives';
import type { AppLocale } from '@/lib/i18n';
import { ArticleJsonLd, BreadcrumbJsonLd, JsonLd } from '@/components/JsonLd';
import { breadcrumbLabels, localizedUrl } from '@/lib/ai-search';

/**
 * #7869 — Metadata serveur par comparatif (/alternatives/[slug]) : canonical,
 * hreflang, og:type article + JSON-LD Article/Breadcrumb/FAQPage émis côté
 * serveur (visibles des crawlers IA sans exécution JS — pattern blog #AI-SEO).
 */

async function resolveLocale(): Promise<AppLocale> {
  // #4004 : ?lang= normalisé en en-tête x-vitrine-lang par le middleware.
  const headerList = await headers();
  return (headerList.get('x-vitrine-lang') ?? 'fr') as AppLocale;
}

export async function generateMetadata({
  params,
}: {
  params: Promise<{ slug: string }>;
}): Promise<Metadata> {
  const { slug } = await params;
  const lang = await resolveLocale();
  const page = getAlternativePage(slug, lang);

  if (!page) {
    notFound();
  }

  return generateSEOMetadata({
    title: page.title,
    description: page.metaDescription,
    ogType: 'article',
    canonical: `${SITE_URL}/alternatives/${page.slug}`,
    locale: lang,
    publishedTime:
      page.reviewedAt instanceof Date ? page.reviewedAt.toISOString() : undefined,
  });
}

export default async function AlternativeLayout({
  children,
  params,
}: {
  children: React.ReactNode;
  params: Promise<{ slug: string }>;
}) {
  const { slug } = await params;
  const lang = await resolveLocale();
  const page = getAlternativePage(slug, lang);

  if (!page) {
    notFound();
  }

  const labels = breadcrumbLabels(lang);
  const pageUrl = localizedUrl(`/alternatives/${page.slug}`, lang);

  return (
    <>
      <ArticleJsonLd
        title={page.title}
        description={page.metaDescription}
        url={pageUrl}
        image={`${SITE_URL}/og-image.png`}
        datePublished={new Date(page.reviewedAt).toISOString()}
        author="Leopardo"
        inLanguage={lang}
      />
      <JsonLd data={generateFAQSchema(page.faqs)} />
      <BreadcrumbJsonLd
        items={[
          { name: labels.home, url: localizedUrl('/', lang) },
          { name: alternativesHubLabel[lang] ?? alternativesHubLabel.fr, url: localizedUrl('/alternatives', lang) },
          { name: page.title, url: pageUrl },
        ]}
      />
      {children}
    </>
  );
}
