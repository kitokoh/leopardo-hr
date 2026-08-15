import { Metadata } from 'next';
import { canonicalUrl, generateMetadata as generateSEOMetadata, pageMetadata } from '@/modules/vitrine/lib/seo';

export const metadata: Metadata = generateSEOMetadata({
  title: pageMetadata.videos.title,
  description: pageMetadata.videos.description,
  keywords: pageMetadata.videos.keywords,
  ogImage: pageMetadata.videos.ogImage,
  ogType: 'website',
  canonical: canonicalUrl('/videos'),
});

export default function VideosLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return children;
}
