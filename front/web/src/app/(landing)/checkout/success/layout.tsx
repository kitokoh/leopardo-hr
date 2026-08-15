import { SITE_URL } from '@/lib/site-url';
import { Metadata } from 'next';
import { generateMetadata as generateSEOMetadata, pageMetadata } from '@/modules/vitrine/lib/seo';

// #3807 : /checkout/success héritait du canonical /checkout du layout parent —
// les crawlers voyaient la page de confirmation pointer vers le checkout.
// Canonical propre + noindex conservé (page de conversion, pas de SEO).
export const metadata: Metadata = generateSEOMetadata({
  title: pageMetadata.checkout.title,
  description: pageMetadata.checkout.description,
  keywords: pageMetadata.checkout.keywords,
  ogImage: pageMetadata.checkout.ogImage,
  ogType: 'website',
  canonical: `${SITE_URL}/checkout/success`,
  robots: pageMetadata.checkout.robots,
});

export default function CheckoutSuccessLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return children;
}
