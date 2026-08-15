import { Metadata } from 'next';
import { generateMetadata as generateSEOMetadata, pageMetadata } from '@/modules/vitrine/lib/seo';
import { getSiteUrl } from '@/lib/site';

export const metadata: Metadata = generateSEOMetadata({
  title: pageMetadata.caseStudies.title,
  description: pageMetadata.caseStudies.description,
  keywords: pageMetadata.caseStudies.keywords,
  ogImage: pageMetadata.caseStudies.ogImage,
  ogType: 'website',
  canonical: `${getSiteUrl()}/case-studies`,
});

export default function CaseStudiesLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return children;
}
