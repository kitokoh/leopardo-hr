import { Metadata } from 'next';
import { canonicalUrl, generateMetadata as generateSEOMetadata, pageMetadata } from '@/modules/vitrine/lib/seo';

export const metadata: Metadata = generateSEOMetadata({
  title: pageMetadata.checkout.title,
  description: pageMetadata.checkout.description,
  keywords: pageMetadata.checkout.keywords,
  ogImage: pageMetadata.checkout.ogImage,
  ogType: 'website',
  canonical: canonicalUrl('/checkout'),
  robots: pageMetadata.checkout.robots,
});

export default function CheckoutLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return children;
}
