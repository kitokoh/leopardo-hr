import { SITE_URL } from '@/lib/site-url';
import { Metadata } from 'next';
import { generateMetadata as generateSEOMetadata, pageMetadata } from '@/modules/vitrine/lib/seo';

export const metadata: Metadata = generateSEOMetadata({
  title: pageMetadata.guides.title,
  description: pageMetadata.guides.description,
  keywords: pageMetadata.guides.keywords,
  ogImage: pageMetadata.guides.ogImage,
  ogType: 'website',
  canonical: `${SITE_URL}/guides`,
});

export default function GuidesLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return children;
}
