import type { Metadata } from 'next';
import Link from 'next/link';
import { notFound } from 'next/navigation';
import { MapPin, Star } from 'lucide-react';
import { normalizeLocale } from '@/lib/i18n';
import { catalogDirection, t } from '@/lib/i18n/locale-catalog';
import { SITE_URL } from '@/lib/site-url';
import { JsonLd } from '@/components/JsonLd';
import { getPublicShop, type PublicShopProfile } from '@/lib/retail-public-api';
import PublicContactCta from '@/app/decouvrir/public-contact-cta';

/**
 * P1-02 — page publique SEO d'une boutique Leopardo Marché (`/boutiques/{slug}`).
 *
 * SSR indexable (modèle : `/restaurants/{slug}` RESTO-903) : metadata dynamique
 * + JSON-LD schema.org `Store`, profil (nom, ville, description, note agrégée,
 * catégories) rendus côté serveur via `GET /public/market/sellers/{sellerSlug}`
 * (404 fail-closed → `notFound()`).
 *
 * Pourquoi cette page existe : `RetailDiscoverableProvider` publie désormais
 * `url = /boutiques/{slug}` dans `/public/discover`. Sans elle, un résultat
 * Retail pointait vers un parcours d'achat (`front/marketplace`) au lieu d'une
 * fiche profil — c'est le constat P1-02 de l'audit Phase 1.
 *
 * Aucune logique métier n'est dupliquée : le périmètre de publication (opt-in
 * `retail_online_settings.enabled`, feature `retail`, société non
 * suspendue/expirée) est appliqué par le backend, et le parcours d'achat reste
 * porté par `front/marketplace` — cette page ne fait que présenter le profil et
 * ouvrir le contact.
 */

interface ShopPageProps {
  params: Promise<{ slug: string }>;
  searchParams: Promise<{ lang?: string }>;
}

export async function generateMetadata({ params, searchParams }: ShopPageProps): Promise<Metadata> {
  const { slug } = await params;
  const { lang } = await searchParams;
  const locale = normalizeLocale(lang ?? '');
  const shop = await getPublicShop(slug);

  if (!shop) {
    return { title: t(locale, 'discovery.shopNotFoundTitle') };
  }

  const title = shop.city ? `${shop.name} — ${shop.city}` : shop.name;
  const description = shop.description ?? t(locale, 'discovery.shopTitle');

  return {
    title,
    description,
    alternates: { canonical: `${SITE_URL}/boutiques/${shop.slug}` },
    robots: { index: true, follow: true },
    openGraph: { title, description, type: 'website' },
  };
}

function shopJsonLd(shop: PublicShopProfile): Record<string, unknown> {
  const data: Record<string, unknown> = {
    '@context': 'https://schema.org',
    '@type': 'Store',
    name: shop.name,
    url: `${SITE_URL}/boutiques/${shop.slug}`,
  };

  if (shop.description) {
    data.description = shop.description;
  }
  if (shop.city) {
    data.address = { '@type': 'PostalAddress', addressLocality: shop.city };
  }
  if (shop.contact_phone) {
    data.telephone = shop.contact_phone;
  }
  if (shop.contact_email) {
    data.email = shop.contact_email;
  }
  if (shop.rating_avg !== null && shop.rating_count > 0) {
    data.aggregateRating = {
      '@type': 'AggregateRating',
      ratingValue: shop.rating_avg,
      reviewCount: shop.rating_count,
      bestRating: 5,
      worstRating: 1,
    };
  }

  return data;
}

export default async function ShopPage({ params, searchParams }: ShopPageProps) {
  const { slug } = await params;
  const { lang } = await searchParams;
  const locale = normalizeLocale(lang ?? '');
  const dir = catalogDirection(locale);

  const shop = await getPublicShop(slug);

  if (!shop) {
    notFound();
  }

  return (
    <main
      dir={dir}
      className="min-h-screen bg-gradient-to-br from-slate-50 via-emerald-50/40 to-teal-50/40 px-4 py-10"
    >
      <JsonLd data={shopJsonLd(shop)} />
      <div className="mx-auto max-w-5xl space-y-8">
        <Link
          href="/decouvrir"
          className="inline-block text-sm font-semibold text-slate-600 underline"
        >
          {t(locale, 'discovery.shopBackToDirectory')}
        </Link>

        <header className="rounded-3xl border border-white/40 bg-white/80 p-6 shadow-sm backdrop-blur-xl">
          <div className="flex flex-wrap items-center gap-3">
            <h1 className="text-3xl font-black tracking-tight text-slate-950">{shop.name}</h1>
            {shop.rating_avg !== null && shop.rating_count > 0 ? (
              <span className="inline-flex items-center gap-1 rounded-xl bg-amber-50 px-3 py-1 text-sm font-black text-amber-700">
                <Star className="h-4 w-4" aria-hidden="true" />
                {shop.rating_avg.toFixed(1)}
                <span className="font-medium text-amber-600">
                  ({shop.rating_count} {t(locale, 'discovery.shopRatingSuffix')})
                </span>
              </span>
            ) : null}
          </div>

          <p className="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-slate-500">
            <span className="font-bold text-emerald-700">{t(locale, 'discovery.shopTitle')}</span>
            {shop.city ? (
              <span className="flex items-center gap-1">
                <MapPin className="h-3.5 w-3.5" aria-hidden="true" />
                {shop.city}
              </span>
            ) : null}
            <span>
              {shop.products_count} {t(locale, 'discovery.shopProductsCount')}
            </span>
          </p>

          {shop.description ? (
            <section aria-label={t(locale, 'discovery.shopAboutTitle')} className="mt-4">
              <p className="max-w-3xl text-sm text-slate-600">{shop.description}</p>
            </section>
          ) : null}

          <div className="mt-6">
            <PublicContactCta
              locale={locale}
              targetType="retail_shop"
              targetSlug={shop.slug}
              targetName={shop.name}
            />
          </div>
        </header>

        {shop.categories.length > 0 ? (
          <section className="rounded-3xl border border-white/40 bg-white/80 p-6 shadow-sm backdrop-blur-xl">
            <h2 className="mb-3 text-lg font-black tracking-tight text-slate-950">
              {t(locale, 'discovery.shopCategoriesTitle')}
            </h2>
            <ul className="flex flex-wrap gap-2">
              {shop.categories.map((category) => (
                <li
                  key={category.id}
                  className="rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-sm text-slate-700"
                >
                  {category.name}
                  <span className="ml-2 text-xs text-slate-400">{category.products_count}</span>
                </li>
              ))}
            </ul>
          </section>
        ) : null}
      </div>
    </main>
  );
}
