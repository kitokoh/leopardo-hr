import { SITE_URL } from '@/lib/site-url';
import { Metadata } from 'next';
import { pageMetadata, generateMetadata as generateSEOMetadata } from '@/modules/vitrine/lib/seo';

export const metadata: Metadata = generateSEOMetadata({
  ...pageMetadata.employes,
  canonical: `${SITE_URL}/employes`,
});

export default function EmployesLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return children;
}
