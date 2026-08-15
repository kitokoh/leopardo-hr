import { SITE_URL } from '@/lib/site-url';
import { generateMetadata as generateSEOMetadata } from '@/modules/vitrine/lib/seo';
import { pageMetadata } from '@/modules/vitrine/lib/seo';
import type { Metadata } from 'next';

export const metadata: Metadata = generateSEOMetadata({
  ...pageMetadata.comptabilite,
  canonical: `${SITE_URL}/comptabilite`,
});

export default function ComptabiliteLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return children;
}
