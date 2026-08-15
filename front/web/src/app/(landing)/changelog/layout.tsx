import { Metadata } from 'next';
import { generateMetadata as generateSEOMetadata } from '@/modules/vitrine/lib/seo';
import { canonicalUrl, pageMetadata } from '@/modules/vitrine/lib/seo';

export const metadata: Metadata = generateSEOMetadata({
  title: pageMetadata.changelog.title,
  description: pageMetadata.changelog.description,
  keywords: pageMetadata.changelog.keywords,
  ogImage: pageMetadata.changelog.ogImage,
  ogType: 'website',
  canonical: canonicalUrl('/changelog'),
});

export default function ChangelogLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return children;
}
