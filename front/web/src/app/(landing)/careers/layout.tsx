import { Metadata } from 'next';
import { generateMetadata as generateSEOMetadata, pageMetadata } from '@/modules/vitrine/lib/seo';

export const metadata: Metadata = generateSEOMetadata({
  title: pageMetadata.careers.title,
  description: pageMetadata.careers.description,
  keywords: pageMetadata.careers.keywords,
  ogImage: pageMetadata.careers.ogImage,
  ogType: 'website',
  canonical: 'https://leopardo.com/careers',
});

export default function CareersLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return children;
}
