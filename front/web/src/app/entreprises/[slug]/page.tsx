import type { Metadata } from 'next';
import Link from 'next/link';
import { notFound } from 'next/navigation';
import { Globe2, MapPin } from 'lucide-react';
import { normalizeLocale } from '@/lib/i18n';
import { catalogDirection, t } from '@/lib/i18n/locale-catalog';
import { SITE_URL } from '@/lib/site-url';
import { JsonLd } from '@/components/JsonLd';
import { getPublicTradeCompany, getPublicTradeCompanyProducts, type PublicTradeCompanyProfile, type PublicTradeProduct } from '@/lib/trade-public-api';
import PublicContactCta from '@/app/decouvrir/public-contact-cta';
import {
  TRADE_SURFACE,
  SurfaceNotice,
  SurfacePagination,
  SurfaceSearchBar,
  filterByQuery,
  paginateList,
} from '@/modules/public-surfaces';

/** Catégories de produits affichées par page sur la fiche d'une entreprise. */
const CATEGORIES_PER_PAGE = 24;

/**
 * P2-01 (#8802) — page publique SEO d'une entreprise de commerce international
 * / B2B (`/entreprises/{slug}`, BC-36 TRADE).
 *
 * SSR indexable (même modèle que `/boutiques/{slug}` et `/operateurs/{slug}`) :
 * metadata dynamique + JSON-LD schema.org `Organization`, profil (nom
 * commercial, ville, pays, description, catégories produits, directions
 * import/export) rendus côté serveur via
 * `GET /public/trade/companies/{slug}` (404 fail-closed → `notFound()`).
 *
 * Pourquoi cette page existe : `TradeDiscoverableProvider` publie
 * `url = /entreprises/{slug}` dans `/public/discover`. Sans elle, un résultat de
 * cette verticale serait un lien mort — exactement le défaut corrigé par
 * #8794 pour Restaurant, puis par P1-02 pour Retail et VTC. La garde
 * `PublicDiscoverableProviderContractTest` échoue d'ailleurs explicitement
 * quand un provider déclare un segment sans page correspondante.
 *
 * Aucune logique métier n'est dupliquée : le périmètre de publication (opt-in
 * `is_public`, slug public unique global, société non suspendue/expirée,
 * feature `trade`) est appliqué par le backend, et cette page ne fait que
 * présenter le profil et ouvrir le contact.
 */

interface CompanyPageProps {
  params: Promise<{ slug: string }>;
  searchParams: Promise<{ lang?: string; q?: string; page?: string }>;
}

export async function generateMetadata({ params, searchParams }: CompanyPageProps): Promise<Metadata> {
  const { slug } = await params;
  const { lang } = await searchParams;
  const locale = normalizeLocale(lang ?? '');
  const company = await getPublicTradeCompany(slug);

  if (!company) {
    return { title: t(locale, 'discovery.companyNotFoundTitle') };
  }

  const title = company.city ? `${company.name} — ${company.city}` : company.name;
  const description = company.description ?? t(locale, 'discovery.companyTitle');

  return {
    title,
    description,
    alternates: { canonical: `${SITE_URL}/entreprises/${company.slug}` },
    robots: { index: true, follow: true },
    openGraph: { title, description, type: 'website' },
  };
}

function companyJsonLd(company: PublicTradeCompanyProfile): Record<string, unknown> {
  const data: Record<string, unknown> = {
    '@context': 'https://schema.org',
    '@type': 'Organization',
    name: company.name,
    url: `${SITE_URL}/entreprises/${company.slug}`,
  };

  if (company.description) {
    data.description = company.description;
  }
  if (company.city) {
    data.address = { '@type': 'PostalAddress', addressLocality: company.city };
  }
  if (company.country) {
    data.address = {
      ...(data.address as Record<string, unknown> | undefined),
      addressCountry: company.country,
    };
  }
  if (company.product_categories.length > 0) {
    data.knowsAbout = company.product_categories;
  }

  return data;
}

export default async function CompanyPage({ params, searchParams }: CompanyPageProps) {
  const { slug } = await params;
  const search = await searchParams;
  const locale = normalizeLocale(search.lang ?? '');
  const dir = catalogDirection(locale);

  const company = await getPublicTradeCompany(slug);

  if (!company) {
    notFound();
  }

  // #9768 — le catalogue réel du vendeur. `null` = API indisponible ou profil
  // non publiable : on affiche « aucun produit » plutôt qu'une erreur, mais on
  // ne fabrique jamais de produit.
  const products = (await getPublicTradeCompanyProducts(slug)) ?? [];

  const query = (search.q ?? '').slice(0, 120) || undefined;
  const pageNumber = Number.parseInt(search.page ?? '', 10);
  const categories = paginateList(
    filterByQuery(company.product_categories, query, (category) => [category]),
    Number.isFinite(pageNumber) ? pageNumber : undefined,
    CATEGORIES_PER_PAGE,
  );

  const directions: string[] = [];
  if (company.import_enabled) {
    directions.push(t(locale, 'discovery.companyImport'));
  }
  if (company.export_enabled) {
    directions.push(t(locale, 'discovery.companyExport'));
  }
  if (company.domestic_scope) {
    directions.push(t(locale, 'discovery.companyDomestic'));
  }

  return (
    <div
      dir={dir}
      data-surface={TRADE_SURFACE.vertical}
      className="min-h-screen px-4 py-10"
      style={{ backgroundColor: TRADE_SURFACE.palette.surface }}
    >
      <JsonLd data={companyJsonLd(company)} />
      <div className="mx-auto max-w-5xl space-y-8">
        <Link
          href="/decouvrir"
          className="inline-block text-sm font-semibold text-slate-600 underline"
        >
          {t(locale, 'discovery.shopBackToDirectory')}
        </Link>

        <header className="rounded-3xl border border-white/40 bg-white/80 p-6 shadow-sm backdrop-blur-xl">
          <div className="flex flex-wrap items-center gap-3">
            <h1 className="text-3xl font-black tracking-tight text-slate-950">{company.name}</h1>
          </div>

          <p className="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-slate-500">
            <span className="font-bold text-sky-700">{t(locale, 'discovery.companyTitle')}</span>
            {company.city ? (
              <span className="flex items-center gap-1">
                <MapPin className="h-3.5 w-3.5" aria-hidden="true" />
                {company.city}
                {company.country ? ` (${company.country})` : ''}
              </span>
            ) : null}
            {directions.length > 0 ? (
              <span className="flex items-center gap-1">
                <Globe2 className="h-3.5 w-3.5" aria-hidden="true" />
                {directions.join(' · ')}
              </span>
            ) : null}
          </p>

          {company.description ? (
            <section aria-label={t(locale, 'discovery.shopAboutTitle')} className="mt-4">
              <p className="max-w-3xl text-sm text-slate-600">{company.description}</p>
            </section>
          ) : null}

          {/* Trade n'expose QUE le contact : pas de commande ni de réservation
              publique dans BC-36 (voir `TradeDiscoverableProvider::supportedActions`).
              Afficher un bouton de commande ici contredirait la capacité déclarée. */}
          <div className="mt-6">
            <PublicContactCta
              locale={locale}
              targetType="trade_company"
              targetSlug={company.slug}
              targetName={company.name}
            />
          </div>
        </header>

        {/* #9768 — catalogue réel du vendeur. C'est ce qu'un acheteur
            professionnel vient chercher : quoi, combien, à partir de quel
            volume, à quel prix indicatif. Sans lui, la fiche n'affichait que
            des catégories — elle qualifiait l'offre sans la montrer. */}
        <section className="rounded-3xl border border-white/40 bg-white/80 p-6 shadow-sm backdrop-blur-xl">
          <h2 className="mb-3 text-lg font-black tracking-tight text-slate-950">
            {t(locale, 'discovery.companyProductsTitle')}
          </h2>

          {products.length === 0 ? (
            <SurfaceNotice identity={TRADE_SURFACE}>
              {t(locale, 'discovery.companyProductsEmpty')}
            </SurfaceNotice>
          ) : (
            <ul className="grid grid-cols-1 gap-3 sm:grid-cols-2">
              {products.map((product: PublicTradeProduct) => {
                const meta = [
                  product.category,
                  product.origin_country
                    ? `${t(locale, 'discovery.companyProductOrigin')}: ${product.origin_country}`
                    : null,
                  product.moq_quantity
                    ? `${t(locale, 'discovery.companyProductMoq')}: ${product.moq_quantity}${product.unit ? ` ${product.unit}` : ''}`
                    : null,
                ].filter((part): part is string => typeof part === 'string' && part !== '');

                const price =
                  product.indicative_unit_price_minor !== null && product.currency
                    ? `${(product.indicative_unit_price_minor / 100).toLocaleString(locale)} ${product.currency}`
                    : null;

                return (
                  <li
                    key={product.name}
                    className="rounded-2xl border border-slate-200 bg-white p-4"
                  >
                    <p className="text-sm font-bold text-slate-900">{product.name}</p>
                    {product.description ? (
                      <p className="mt-1 text-xs text-slate-600">{product.description}</p>
                    ) : null}
                    {meta.length > 0 ? (
                      <p className="mt-2 text-xs text-slate-500">{meta.join(' · ')}</p>
                    ) : null}
                    <p className="mt-2 text-xs font-semibold text-sky-700">
                      {price
                        ? `${t(locale, 'discovery.companyProductPriceIndicative')} — ${price}`
                        : t(locale, 'discovery.companyProductPriceOnRequest')}
                    </p>
                  </li>
                );
              })}
            </ul>
          )}
        </section>

        {company.product_categories.length > 0 ? (
          <section className="rounded-3xl border border-white/40 bg-white/80 p-6 shadow-sm backdrop-blur-xl">
            <h2 className="mb-3 text-lg font-black tracking-tight text-slate-950">
              {t(locale, 'discovery.shopCategoriesTitle')}
            </h2>

            <SurfaceSearchBar
              identity={TRADE_SURFACE}
              basePath={`/entreprises/${company.slug}`}
              defaultQuery={query}
              labels={{
                searchLabel: t(locale, 'discovery.searchLabel'),
                searchPlaceholder: t(locale, 'discovery.searchPlaceholder'),
                searchSubmit: t(locale, 'discovery.search'),
                searchReset: t(locale, 'discovery.clearNear'),
              }}
            />

            {categories.items.length === 0 ? (
              <SurfaceNotice identity={TRADE_SURFACE}>
                {t(locale, 'discovery.noResults')}
              </SurfaceNotice>
            ) : (
              <ul className="flex flex-wrap gap-2">
                {categories.items.map((category) => (
                  <li
                    key={category}
                    className="rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-sm text-slate-700"
                  >
                    {category}
                  </li>
                ))}
              </ul>
            )}

            <SurfacePagination
              identity={TRADE_SURFACE}
              basePath={`/entreprises/${company.slug}`}
              page={categories.page}
              lastPage={categories.lastPage}
              searchParams={{ q: query }}
              labels={{
                previous: t(locale, 'discovery.previous'),
                next: t(locale, 'discovery.next'),
                pageOf: `${t(locale, 'discovery.pageLabel')} {page} / {last}`,
              }}
            />
          </section>
        ) : null}
      </div>
    </div>
  );
}
