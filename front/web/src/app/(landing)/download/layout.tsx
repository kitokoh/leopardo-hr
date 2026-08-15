import { SITE_URL } from '@/lib/site-url';
import { Metadata } from 'next';
import { generateMetadata as generateSEOMetadata } from '@/modules/vitrine/lib/seo';
import { pageMetadata } from '@/modules/vitrine/lib/seo';

export const metadata: Metadata = generateSEOMetadata({
  title: pageMetadata.download.title,
  description: pageMetadata.download.description,
  keywords: pageMetadata.download.keywords,
  ogImage: pageMetadata.download.ogImage,
  ogType: 'website',
  canonical: `${SITE_URL}/download`,
});

export default function DownloadLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return children;
}
