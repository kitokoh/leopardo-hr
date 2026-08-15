import { SITE_URL } from '@/lib/site-url';
import { Metadata } from 'next';
import { headers } from 'next/headers';
import { generateMetadata as generateSEOMetadata, localizedPageMetadata, pageMetadata, resolveSsrLang } from '@/modules/vitrine/lib/seo';
import { getCaseStudy } from '@/modules/vitrine/lib/case-studies';

// #3435 : metadata par slug (title/description/canonical propres) au lieu du
// canonical fixe du layout pour les 12 études de cas.
export async function generateMetadata({
  params,
}: {
  params: Promise<{ slug?: string }>;
}): Promise<Metadata> {
  const { slug } = await params;
  const headerList = await headers();
  const lang = headerList.get('x-lang') ?? resolveSsrLang(headerList.get('accept-language'));
  const meta = localizedPageMetadata('caseStudies', lang);
  const listing = generateSEOMetadata({
    title: meta.title,
    description: meta.description,
    keywords: pageMetadata.caseStudies.keywords,
    ogImage: pageMetadata.caseStudies.ogImage,
    ogType: 'website',
    canonical: `${SITE_URL}/case-studies`,
  });
  if (!slug) {
    return listing;
  }

  const study = getCaseStudy(slug);
  if (!study) {
    return listing;
  }

  return generateSEOMetadata({
    title: study.title,
    description: study.description,
    ogType: 'website',
    canonical: `${SITE_URL}/case-studies/${study.slug}`,
  });
}

export default function CaseStudiesLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return children;
}
