import { Metadata } from 'next';
import { generateMetadata as generateSEOMetadata, pageMetadata } from '@/modules/vitrine/lib/seo';
import { getSiteUrl } from '@/lib/site';

export const metadata: Metadata = generateSEOMetadata({
  title: pageMetadata.videos.title,
  description: pageMetadata.videos.description,
  keywords: pageMetadata.videos.keywords,
  ogImage: pageMetadata.videos.ogImage,
  ogType: 'website',
  canonical: `${getSiteUrl()}/videos`,
});

export default function VideosLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return children;
}
