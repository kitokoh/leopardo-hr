import { SITE_URL } from '@/lib/site-url';
import { Metadata } from 'next';
import { generateMetadata as generateSEOMetadata, pageMetadata } from '@/modules/vitrine/lib/seo';
import { getCaseStudy } from '@/modules/vitrine/lib/case-studies';

const listingMetadata: Metadata = generateSEOMetadata({
  title: pageMetadata.caseStudies.title,
  description: pageMetadata.caseStudies.description,
  keywords: pageMetadata.caseStudies.keywords,
  ogImage: pageMetadata.caseStudies.ogImage,
  ogType: 'website',
  canonical: `${SITE_URL}/case-studies`,
});

// #3435 : metadata par slug (title/description/canonical propres) au lieu du
// canonical fixe du layout pour les 12 études de cas.
export async function generateMetadata({
  params,
}: {
  params: Promise<{ slug?: string }>;
}): Promise<Metadata> {
  const { slug } = await params;
  if (!slug) {
    return listingMetadata;
  }

  const study = getCaseStudy(slug);
  if (!study) {
    return listingMetadata;
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
