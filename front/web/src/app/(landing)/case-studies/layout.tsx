import { SITE_URL } from '@/lib/site-url';
import { Metadata } from 'next';
import { generateMetadata as generateSEOMetadata, pageMetadata } from '@/modules/vitrine/lib/seo';

export const metadata: Metadata = generateSEOMetadata({
  title: pageMetadata.caseStudies.title,
  description: pageMetadata.caseStudies.description,
  keywords: pageMetadata.caseStudies.keywords,
  ogImage: pageMetadata.caseStudies.ogImage,
  ogType: 'website',
  canonical: `${SITE_URL}/case-studies`,
});

export default function CaseStudiesLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return children;
}
