import { Metadata } from 'next';
import { canonicalUrl, generateMetadata as generateSEOMetadata, pageMetadata  } from '@/modules/vitrine/lib/seo';

export const metadata: Metadata = generateSEOMetadata({
  title: pageMetadata.mobile.title,
  description: pageMetadata.mobile.description,
  keywords: pageMetadata.mobile.keywords,
  ogImage: pageMetadata.mobile.ogImage,
  ogType: 'website',
  canonical: canonicalUrl('/mobile'),
});

export default function MobileLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return children;
}
