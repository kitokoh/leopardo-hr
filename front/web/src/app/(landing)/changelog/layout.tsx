import { Metadata } from 'next';
import { generateMetadata as generateSEOMetadata } from '@/modules/vitrine/lib/seo';
import { pageMetadata } from '@/modules/vitrine/lib/seo';

export const metadata: Metadata = generateSEOMetadata({
  title: pageMetadata.changelog.title,
  description: pageMetadata.changelog.description,
  keywords: pageMetadata.changelog.keywords,
  ogImage: pageMetadata.changelog.ogImage,
  ogType: 'website',
  canonical: 'https://leopardo.com/changelog',
});

export default function ChangelogLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return children;
}
