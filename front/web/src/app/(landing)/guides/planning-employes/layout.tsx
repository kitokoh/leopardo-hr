import { SITE_URL } from '@/lib/site-url';
import { Metadata } from 'next';
import { generateMetadata as generateSEOMetadata, pageMetadata } from '@/modules/vitrine/lib/seo';

export const metadata: Metadata = generateSEOMetadata({
  title: pageMetadata.guidePlanningEmployes.title,
  description: pageMetadata.guidePlanningEmployes.description,
  keywords: pageMetadata.guidePlanningEmployes.keywords,
  ogImage: pageMetadata.guidePlanningEmployes.ogImage,
  ogType: 'article',
  canonical: `${SITE_URL}/guides/planning-employes`,
});

export default function GuidesPlanningEmployesLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return children;
}
