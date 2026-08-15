import { Metadata } from 'next';
import { generateMetadata as generateSEOMetadata } from '@/modules/vitrine/lib/seo';
import { pageMetadata } from '@/modules/vitrine/lib/seo';
import { getSiteUrl } from '@/lib/site';

export const metadata: Metadata = generateSEOMetadata({
  title: pageMetadata.about.title,
  description: pageMetadata.about.description,
  keywords: pageMetadata.about.keywords,
  ogImage: pageMetadata.about.ogImage,
  ogType: 'website',
  canonical: `${getSiteUrl()}/about`,
});

export default function AboutLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return children;
}
