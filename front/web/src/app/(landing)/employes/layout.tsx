import { Metadata } from 'next';
import { pageMetadata, generateMetadata as generateSEOMetadata } from '@/modules/vitrine/lib/seo';

export const metadata: Metadata = generateSEOMetadata(pageMetadata.employes);

export default function EmployesLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return children;
}
