import { Metadata } from 'next';
import { generateMetadata as generateSEOMetadata, pageMetadata } from '@/modules/vitrine/lib/seo';

export const metadata: Metadata = generateSEOMetadata({
  title: pageMetadata.branding.title,
  description: pageMetadata.branding.description,
  keywords: pageMetadata.branding.keywords,
  ogImage: pageMetadata.branding.ogImage,
  ogType: 'website',
  canonical: 'https://leopardo.com/branding',
});

export default function BrandingLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return children;
}
