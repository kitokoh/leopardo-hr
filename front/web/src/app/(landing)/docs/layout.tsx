import { Metadata } from 'next';
import { generateMetadata as generateSEOMetadata } from '@/modules/vitrine/lib/seo';
import { canonicalUrl, pageMetadata } from '@/modules/vitrine/lib/seo';

export const metadata: Metadata = generateSEOMetadata({
  title: pageMetadata.docs.title,
  description: pageMetadata.docs.description,
  keywords: pageMetadata.docs.keywords,
  ogImage: pageMetadata.docs.ogImage,
  ogType: 'website',
  canonical: canonicalUrl('/docs'),
});

export default function DocsLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return children;
}
