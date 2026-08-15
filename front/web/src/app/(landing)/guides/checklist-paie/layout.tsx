import { SITE_URL } from '@/lib/site-url';
import { Metadata } from 'next';
import { generateMetadata as generateSEOMetadata, pageMetadata } from '@/modules/vitrine/lib/seo';

export const metadata: Metadata = generateSEOMetadata({
  title: pageMetadata.guideChecklistPaie.title,
  description: pageMetadata.guideChecklistPaie.description,
  keywords: pageMetadata.guideChecklistPaie.keywords,
  ogImage: pageMetadata.guideChecklistPaie.ogImage,
  ogType: 'article',
  canonical: `${SITE_URL}/guides/checklist-paie`,
});

export default function GuidesChecklistPaieLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return children;
}
