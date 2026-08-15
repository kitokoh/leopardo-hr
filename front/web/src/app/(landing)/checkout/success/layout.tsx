import { SITE_URL } from '@/lib/site-url';
import { Metadata } from 'next';
import { headers } from 'next/headers';
import { generateMetadata as generateSEOMetadata, localizedPageMetadata, resolveSsrLang, pageMetadata } from '@/modules/vitrine/lib/seo';

// #3807 : /checkout/success héritait du canonical /checkout du layout parent —
// les crawlers voyaient la page de confirmation pointer vers le checkout.
// Canonical propre + noindex conservé (page de conversion, pas de SEO).
export async function generateMetadata(): Promise<Metadata> {
  const headerList = await headers();
  const lang = headerList.get('x-lang') ?? resolveSsrLang(headerList.get('accept-language'));
  const meta = localizedPageMetadata('checkout', lang);
  return generateSEOMetadata({    title: meta.title,
    description: meta.description,
  keywords: pageMetadata.checkout.keywords,
  ogImage: pageMetadata.checkout.ogImage,
  ogType: 'website',
  canonical: `${SITE_URL}/checkout/success`,
  robots: pageMetadata.checkout.robots,
});
}

export default function CheckoutSuccessLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return children;
}
