import { Metadata } from 'next';
import { generateMetadata as generateSEOMetadata } from '@/modules/vitrine/lib/seo';
import { canonicalUrl, pageMetadata } from '@/modules/vitrine/lib/seo';

export const metadata: Metadata = generateSEOMetadata({
  title: pageMetadata.download.title,
  description: pageMetadata.download.description,
  keywords: pageMetadata.download.keywords,
  ogImage: pageMetadata.download.ogImage,
  ogType: 'website',
  canonical: canonicalUrl('/download'),
});

export default function DownloadLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return children;
}
