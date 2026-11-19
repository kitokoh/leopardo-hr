import type { Metadata } from 'next';
import { notFound } from 'next/navigation';

import { normalizeLocale } from '@/lib/i18n';
import { SITE_URL } from '@/lib/site-url';
import { JsonLd } from '@/components/JsonLd';
import { RETAIL_SURFACE, ProductSurface, loadProductView } from '@/modules/public-surfaces';

/**
 * Fiche produit publique (`/produits/{id}`) — #9627.
 *
 * Reprise de `front/marketplace/src/app/produits/[id]/page.tsx`.
 *
 * ## 404 vs panne
 *
 * `loadProductView` rend `null` dans les deux cas. La page ne peut donc pas
 * distinguer « produit inexistant » de « API injoignable » — et c'est
 * volontaire : `notFound()` est le comportement correct pour les deux, parce
 * qu'un produit qu'on ne peut pas charger n'est pas vendable. Afficher une
 * fiche vide serait pire qu'un 404.
 */

interface ProductPageProps {
  params: Promise<{ id: string }>;
  searchParams: Promise<{ lang?: string }>;
}

export async function generateMetadata({ params, searchParams }: ProductPageProps): Promise<Metadata> {
  const { id } = await params;
  const { lang } = await searchParams;
  const locale = normalizeLocale(lang ?? '');
  const card = await loadProductView(id, locale);

  if (card === null) {
    return { title: RETAIL_SURFACE.displayName, robots: { index: false, follow: false } };
  }

  const title = `${card.product.name} — ${card.product.seller.name}`;

  return {
    title,
    description: card.product.description ?? RETAIL_SURFACE.tagline,
    alternates: { canonical: `${SITE_URL}/produits/${id}` },
    robots: { index: true, follow: true },
    openGraph: { title, description: card.product.description ?? RETAIL_SURFACE.tagline, type: 'website' },
  };
}

export default async function ProductPage({ params, searchParams }: ProductPageProps) {
  const { id } = await params;
  const { lang } = await searchParams;
  const locale = normalizeLocale(lang ?? '');

  const card = await loadProductView(id, locale);

  if (card === null) {
    notFound();
  }

  const jsonLd: Record<string, unknown> = {
    '@context': 'https://schema.org',
    '@type': 'Product',
    name: card.product.name,
    url: `${SITE_URL}/produits/${id}`,
    ...(card.product.description ? { description: card.product.description } : {}),
    ...(card.product.imageUrl ? { image: card.product.imageUrl } : {}),
    offers: {
      '@type': 'Offer',
      price: (card.product.priceMinor / 100).toFixed(2),
      priceCurrency: card.product.currency,
      availability: card.purchasable
        ? 'https://schema.org/InStock'
        : 'https://schema.org/OutOfStock',
      seller: { '@type': 'Organization', name: card.product.seller.name },
    },
    ...(card.rating !== null
      ? {
          aggregateRating: {
            '@type': 'AggregateRating',
            ratingValue: card.rating,
            reviewCount: card.product.ratingCount,
            bestRating: 5,
            worstRating: 1,
          },
        }
      : {}),
  };

  return (
    <>
      <JsonLd data={jsonLd} />
      <ProductSurface identity={RETAIL_SURFACE} card={card} locale={locale} />
    </>
  );
}
