import { Metadata } from 'next';
import { generateMetadata as generateSEOMetadata } from '@/modules/vitrine/lib/seo';
import { pageMetadata } from '@/modules/vitrine/lib/seo';

export const metadata: Metadata = generateSEOMetadata({
  title: pageMetadata.download.title,
  description: pageMetadata.download.description,
  keywords: pageMetadata.download.keywords,
  ogImage: pageMetadata.download.ogImage,
  ogType: 'website',
  canonical: 'https://gestionemployer-backend.vercel.app/download',
});

export default function DownloadLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return children;
}
