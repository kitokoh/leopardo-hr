import { SITE_URL } from '@/lib/site-url';
import { Metadata } from 'next';
import { headers } from 'next/headers';
import { generateMetadata as generateSEOMetadata, getPageMetadata } from '@/modules/vitrine/lib/seo';
import { getCaseStudy } from '@/modules/vitrine/lib/case-studies';

// #4004 : listing localisé (FR par défaut, ?lang= pour EN/TR/AR).
async function listingMetadata(lang?: string): Promise<Metadata> {
  const seo = getPageMetadata('caseStudies', lang);
  return generateSEOMetadata({
    ...seo,
    ogType: 'website',
    canonical: `${SITE_URL}/case-studies`,
    locale: lang,
  });
}

// #3435 : metadata par slug (title/description/canonical propres) au lieu du
// canonical fixe du layout pour les 12 études de cas.
// #4004 : ?lang= normalisé par le middleware en en-tête x-vitrine-lang
// (Next 15 ne passe pas searchParams aux generateMetadata des layouts).
export async function generateMetadata({
  params,
}: {
  params: Promise<{ slug?: string }>;
}): Promise<Metadata> {
  const { slug } = await params;
  const headerList = await headers();
  const lang = headerList.get('x-vitrine-lang') ?? undefined;
  if (!slug) {
    return listingMetadata(lang);
  }

  const study = getCaseStudy(slug);
  if (!study) {
    return listingMetadata(lang);
  }

  // #4867 : ogType 'article' pour les études de cas individuelles (contenu
  // éditorial — cohérent avec le schéma OpenGraph et les robots de crawl).
  return generateSEOMetadata({
    title: study.title,
    description: study.description,
    ogType: 'article',
    canonical: `${SITE_URL}/case-studies/${study.slug}`,
    locale: lang,
  });
}

export default function CaseStudiesLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return children;
}
