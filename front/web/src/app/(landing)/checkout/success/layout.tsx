import { SITE_URL } from '@/lib/site-url';
import { Metadata } from 'next';
import { generateMetadata as generateSEOMetadata, getPageMetadata } from '@/modules/vitrine/lib/seo';

// #3807 : /checkout/success héritait du canonical /checkout du layout parent —
// les crawlers voyaient la page de confirmation pointer vers le checkout.
// Canonical propre + noindex conservé (page de conversion, pas de SEO).
export async function generateMetadata({ searchParams }: {
  searchParams: Promise<{ lang?: string }>;
}): Promise<Metadata> {
  const { lang } = await searchParams;
  const seo = getPageMetadata('checkout', lang);
  return generateSEOMetadata({

  title: seo.title,
  description: seo.description,
  keywords: seo.keywords,
  ogImage: seo.ogImage,
  ogType: 'website',
  canonical: `${SITE_URL}/checkout/success`,
  robots: seo.robots,
    locale: lang,
  });
}

export default function CheckoutSuccessLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return children;
}
