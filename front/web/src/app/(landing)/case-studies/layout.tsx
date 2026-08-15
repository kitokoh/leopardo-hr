import { SITE_URL } from '@/lib/site-url';
import { Metadata } from 'next';
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
export async function generateMetadata({
  params,
  searchParams,
}: {
  params: Promise<{ slug?: string }>;
  searchParams: Promise<{ lang?: string }>;
}): Promise<Metadata> {
  const { slug } = await params;
  const { lang } = await searchParams;
  if (!slug) {
    return listingMetadata(lang);
  }

  const study = getCaseStudy(slug);
  if (!study) {
    return listingMetadata(lang);
  }

  return generateSEOMetadata({
    title: study.title,
    description: study.description,
    ogType: 'website',
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
