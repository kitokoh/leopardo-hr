import { SITE_URL } from '@/lib/site-url';
import type { Metadata } from 'next';
import { generateMetadata as generateSEOMetadata, pageMetadata } from '@/modules/vitrine/lib/seo';

export const metadata: Metadata = generateSEOMetadata({
  title: pageMetadata.demo.title,
  description: pageMetadata.demo.description,
  keywords: pageMetadata.demo.keywords,
  ogImage: pageMetadata.demo.ogImage,
  ogType: 'website',
  canonical: `${SITE_URL}/demo`,
});

export default function DemoLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return children;
}
