import { SITE_URL } from '@/lib/site-url';
import { Metadata } from 'next';
import { generateMetadata as generateSEOMetadata, pageMetadata } from '@/modules/vitrine/lib/seo';

export const metadata: Metadata = generateSEOMetadata({
  title: pageMetadata.branding.title,
  description: pageMetadata.branding.description,
  keywords: pageMetadata.branding.keywords,
  ogImage: pageMetadata.branding.ogImage,
  ogType: 'website',
  canonical: `${SITE_URL}/branding`,
});

export default function BrandingLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return children;
}
