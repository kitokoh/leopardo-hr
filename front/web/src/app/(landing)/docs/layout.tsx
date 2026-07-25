import { Metadata } from 'next';
import { generateMetadata as generateSEOMetadata } from '@/modules/vitrine/lib/seo';
import { pageMetadata } from '@/modules/vitrine/lib/seo';

export const metadata: Metadata = generateSEOMetadata({
  title: pageMetadata.docs.title,
  description: pageMetadata.docs.description,
  keywords: pageMetadata.docs.keywords,
  ogImage: pageMetadata.docs.ogImage,
  ogType: 'website',
  canonical: 'https://leopardo.com/docs',
});

export default function DocsLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return children;
}
