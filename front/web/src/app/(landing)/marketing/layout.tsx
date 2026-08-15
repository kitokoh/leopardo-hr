import { SITE_URL } from '@/lib/site-url';
import { Metadata } from 'next';
import { pageMetadata, generateMetadata as generateSEOMetadata } from '@/modules/vitrine/lib/seo';

export const metadata: Metadata = generateSEOMetadata({
  ...pageMetadata.marketing,
  canonical: `${SITE_URL}/marketing`,
});

export default function MarketingLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return children;
}
