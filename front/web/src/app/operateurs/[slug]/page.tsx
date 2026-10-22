import type { Metadata } from 'next';
import Link from 'next/link';
import { notFound } from 'next/navigation';
import { MapPin } from 'lucide-react';
import { normalizeLocale } from '@/lib/i18n';
import { catalogDirection, t } from '@/lib/i18n/locale-catalog';
import { SITE_URL } from '@/lib/site-url';
import { JsonLd } from '@/components/JsonLd';
import { getPublicVtcOperator, type PublicVtcOperatorProfile } from '@/lib/vtc-public-api';
import PublicContactCta from '@/app/decouvrir/public-contact-cta';

/**
 * P1-02 — page publique SEO d'un opérateur VTC/taxi (`/operateurs/{slug}`).
 *
 * SSR indexable (modèle : `/restaurants/{slug}` RESTO-903) : metadata dynamique
 * + JSON-LD schema.org `TaxiService`, identité publique rendue côté serveur via
 * `GET /public/vtc/marketplace/operators/{slug}` (404 fail-closed →
 * `notFound()`).
 *
 * Pourquoi cette page existe : `VtcDiscoverableProvider` publie désormais
 * `url = /operateurs/{slug}` dans `/public/discover`. Sans elle, un résultat VTC
 * n'avait aucune fiche (constat P1-02 de l'audit Phase 1).
 *
 * Ce que cette page ne fait PAS : afficher un prix. La grille tarifaire reste
 * interne au tenant — un devis s'obtient par le contrat public dédié
 * `POST /public/vtc/marketplace/estimate`, jamais depuis une fiche. Aucun
 * bouton de réservation n'est rendu ici : `VtcDiscoverableProvider` ne déclare
 * que l'action `contact` (la course se demande par l'endpoint public dédié),
 * donc afficher un CTA de réservation serait un bouton inopérant.
 */

interface OperatorPageProps {
  params: Promise<{ slug: string }>;
  searchParams: Promise<{ lang?: string }>;
}

export async function generateMetadata({
  params,
  searchParams,
}: OperatorPageProps): Promise<Metadata> {
  const { slug } = await params;
  const { lang } = await searchParams;
  const locale = normalizeLocale(lang ?? '');
  const operator = await getPublicVtcOperator(slug);

  if (!operator) {
    return { title: t(locale, 'discovery.operatorNotFoundTitle') };
  }

  const title = operator.city ? `${operator.name} — ${operator.city}` : operator.name;
  const description = t(locale, 'discovery.operatorEstimateHint');

  return {
    title,
    description,
    alternates: { canonical: `${SITE_URL}/operateurs/${operator.slug}` },
    robots: { index: true, follow: true },
    openGraph: { title, description, type: 'website' },
  };
}

function operatorJsonLd(operator: PublicVtcOperatorProfile): Record<string, unknown> {
  const data: Record<string, unknown> = {
    '@context': 'https://schema.org',
    '@type': 'TaxiService',
    name: operator.name,
    url: `${SITE_URL}/operateurs/${operator.slug}`,
  };

  if (operator.city || operator.country) {
    data.areaServed = {
      '@type': 'Place',
      ...(operator.city ? { name: operator.city } : {}),
      ...(operator.country ? { address: { '@type': 'PostalAddress', addressCountry: operator.country } } : {}),
    };
  }

  return data;
}

export default async function OperatorPage({ params, searchParams }: OperatorPageProps) {
  const { slug } = await params;
  const { lang } = await searchParams;
  const locale = normalizeLocale(lang ?? '');
  const dir = catalogDirection(locale);

  const operator = await getPublicVtcOperator(slug);

  if (!operator) {
    notFound();
  }

  return (
    <main
      dir={dir}
      className="min-h-screen bg-gradient-to-br from-slate-50 via-sky-50/40 to-indigo-50/40 px-4 py-10"
    >
      <JsonLd data={operatorJsonLd(operator)} />
      <div className="mx-auto max-w-5xl space-y-8">
        <Link
          href="/decouvrir"
          className="inline-block text-sm font-semibold text-slate-600 underline"
        >
          {t(locale, 'discovery.operatorBackToDirectory')}
        </Link>

        <header className="rounded-3xl border border-white/40 bg-white/80 p-6 shadow-sm backdrop-blur-xl">
          <h1 className="text-3xl font-black tracking-tight text-slate-950">{operator.name}</h1>

          <p className="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-slate-500">
            <span className="font-bold text-sky-700">{t(locale, 'discovery.operatorTitle')}</span>
            {operator.city ? (
              <span className="flex items-center gap-1">
                <MapPin className="h-3.5 w-3.5" aria-hidden="true" />
                {[operator.city, operator.country].filter(Boolean).join(', ')}
              </span>
            ) : null}
            {operator.currency ? <span>{operator.currency}</span> : null}
          </p>

          <section aria-label={t(locale, 'discovery.operatorAboutTitle')} className="mt-4">
            <h2 className="text-sm font-bold text-slate-700">
              {t(locale, 'discovery.operatorEstimateTitle')}
            </h2>
            <p className="mt-1 max-w-3xl text-sm text-slate-600">
              {t(locale, 'discovery.operatorEstimateHint')}
            </p>
          </section>

          <div className="mt-6">
            <PublicContactCta
              locale={locale}
              targetType="vtc_operator"
              targetSlug={operator.slug}
              targetName={operator.name}
            />
          </div>
        </header>
      </div>
    </main>
  );
}
