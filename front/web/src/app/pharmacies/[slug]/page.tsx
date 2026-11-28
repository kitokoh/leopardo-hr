import type { Metadata } from 'next';
import Link from 'next/link';
import { notFound } from 'next/navigation';
import { MapPin, Phone } from 'lucide-react';

import { JsonLd } from '@/components/JsonLd';
import { normalizeLocale } from '@/lib/i18n';
import { catalogDirection, t } from '@/lib/i18n/locale-catalog';
import {
  PHARMACY_SURFACE,
  SurfaceNotice,
  SurfacePagination,
  SurfaceSearchBar,
  filterByQuery,
  hasServices,
  loadPharmacy,
  paginateList,
  type Pharmacy,
  surfaceUrl,
} from '@/modules/public-surfaces';

/** Services affichés par page sur la fiche d'une officine. */
const SERVICES_PER_PAGE = 12;

/**
 * Fiche publique d'une officine (`/pharmacies/{slug}`), #9592.
 *
 * SSR indexable : metadata dynamique + JSON-LD schema.org `Pharmacy` (sous-type
 * de `MedicalBusiness`). Le périmètre de publication (opt-in
 * `pharmacy_public_profiles.enabled`, slug et nom renseignés, société active)
 * est appliqué par le backend, fail-closed — une officine non publiée répond
 * 404, jamais une fiche vide.
 *
 * Le JSON-LD n'annonce **que** ce qui est publié : ni horaires inventés, ni
 * note agrégée sans avis, ni services non déclarés. Un balisage qui promet plus
 * que la page est un mensonge pour les moteurs comme pour le visiteur.
 */

interface PharmacyPageProps {
  params: Promise<{ slug: string }>;
  searchParams: Promise<{ lang?: string; q?: string; page?: string }>;
}

export async function generateMetadata({
  params,
  searchParams,
}: PharmacyPageProps): Promise<Metadata> {
  const { slug } = await params;
  const { lang } = await searchParams;
  const locale = normalizeLocale(lang ?? '');

  const pharmacy = await loadPharmacy(slug);

  if (!pharmacy) {
    return { title: t(locale, 'discovery.shopNotFoundTitle') };
  }

  const title = pharmacy.city ? `${pharmacy.name} — ${pharmacy.city}` : pharmacy.name;
  const description = pharmacy.description ?? PHARMACY_SURFACE.tagline;

  return {
    title,
    description,
    alternates: { canonical: surfaceUrl(PHARMACY_SURFACE, `/pharmacies/${pharmacy.slug}`) },
    robots: { index: true, follow: true },
    openGraph: { title, description, type: 'website' },
  };
}

function pharmacyJsonLd(pharmacy: Pharmacy): Record<string, unknown> {
  const data: Record<string, unknown> = {
    '@context': 'https://schema.org',
    '@type': 'Pharmacy',
    name: pharmacy.name,
    url: surfaceUrl(PHARMACY_SURFACE, `/pharmacies/${pharmacy.slug}`),
  };

  if (pharmacy.description) {
    data.description = pharmacy.description;
  }

  if (pharmacy.city) {
    data.address = { '@type': 'PostalAddress', addressLocality: pharmacy.city };
  }

  if (pharmacy.services.length > 0) {
    data.availableService = pharmacy.services.map((service) => ({
      '@type': 'MedicalProcedure',
      name: service,
    }));
  }

  return data;
}

export default async function PharmacyPage({ params, searchParams }: PharmacyPageProps) {
  const { slug } = await params;
  const search = await searchParams;
  const locale = normalizeLocale(search.lang ?? '');
  const dir = catalogDirection(locale);
  const palette = PHARMACY_SURFACE.palette;

  const pharmacy = await loadPharmacy(slug);

  if (!pharmacy) {
    notFound();
  }

  const query = (search.q ?? '').slice(0, 120) || undefined;
  const pageNumber = Number.parseInt(search.page ?? '', 10);
  const services = paginateList(
    filterByQuery(pharmacy.services, query, (service) => [service]),
    Number.isFinite(pageNumber) ? pageNumber : undefined,
    SERVICES_PER_PAGE,
  );
  const basePath = `/pharmacies/${pharmacy.slug}`;

  return (
    <div dir={dir} className="min-h-screen px-4 py-10" style={{ backgroundColor: palette.surface }}>
      <JsonLd data={pharmacyJsonLd(pharmacy)} />

      <div className="mx-auto max-w-3xl space-y-6">
        <Link
          href="/pharmacies"
          className="inline-block text-sm font-semibold underline"
          style={{ color: palette.muted }}
        >
          {PHARMACY_SURFACE.displayName}
        </Link>

        <header
          className="rounded-3xl border p-6"
          style={{ borderColor: `${palette.muted}33`, backgroundColor: '#FFFFFF' }}
        >
          <div className="flex flex-wrap items-center gap-3">
            <h1 className="text-3xl font-black tracking-tight" style={{ color: palette.onSurface }}>
              {pharmacy.name}
            </h1>

            {pharmacy.onDuty ? (
              <span
                className="rounded-xl px-3 py-1 text-sm font-black"
                style={{ backgroundColor: `${palette.accent}22`, color: palette.primary }}
              >
                {t(locale, 'discovery.onDutyLabel')}
              </span>
            ) : null}
          </div>

          {pharmacy.city ? (
            <p
              className="mt-2 flex items-center gap-1.5 text-sm"
              style={{ color: palette.muted }}
            >
              <MapPin className="h-4 w-4" aria-hidden="true" />
              {pharmacy.city}
            </p>
          ) : null}

          {pharmacy.description ? (
            <p className="mt-4 max-w-2xl text-sm" style={{ color: palette.onSurface }}>
              {pharmacy.description}
            </p>
          ) : null}
        </header>

        {hasServices(pharmacy) ? (
          <section
            className="rounded-3xl border p-6"
            style={{ borderColor: `${palette.muted}33`, backgroundColor: '#FFFFFF' }}
          >
            <h2 className="mb-3 text-lg font-black tracking-tight" style={{ color: palette.onSurface }}>
              {t(locale, 'discovery.shopCategoriesTitle')}
            </h2>

            <SurfaceSearchBar
              identity={PHARMACY_SURFACE}
              basePath={basePath}
              defaultQuery={query}
              labels={{
                searchLabel: t(locale, 'discovery.searchLabel'),
                searchPlaceholder: t(locale, 'discovery.searchPlaceholder'),
                searchSubmit: t(locale, 'discovery.search'),
                searchReset: t(locale, 'discovery.clearNear'),
              }}
            />

            {services.items.length === 0 ? (
              <SurfaceNotice identity={PHARMACY_SURFACE}>
                {t(locale, 'discovery.noResults')}
              </SurfaceNotice>
            ) : (
              <ul className="flex flex-wrap gap-2">
                {services.items.map((service: string) => (
                  <li
                    key={service}
                    className="rounded-xl border px-3 py-1.5 text-sm"
                    style={{ borderColor: `${palette.muted}33`, color: palette.onSurface }}
                  >
                    {service}
                  </li>
                ))}
              </ul>
            )}

            <SurfacePagination
              identity={PHARMACY_SURFACE}
              basePath={basePath}
              page={services.page}
              lastPage={services.lastPage}
              searchParams={{ q: query }}
              labels={{
                previous: t(locale, 'discovery.previous'),
                next: t(locale, 'discovery.next'),
                pageOf: `${t(locale, 'discovery.pageLabel')} {page} / {last}`,
              }}
            />
          </section>
        ) : null}

        {/* Aucun numéro n'est publié par le provider : on n'affiche pas un CTA
            « Appeler » qui mènerait à un numéro inexistant. Le contact passe
            par la passerelle commune (action `contact` du provider). */}
        {pharmacy.onDuty ? (
          <p
            className="flex items-center gap-1.5 text-sm"
            style={{ color: palette.muted }}
          >
            <Phone className="h-4 w-4" aria-hidden="true" />
            {t(locale, 'discovery.onDutyHint')}
          </p>
        ) : null}
      </div>
    </div>
  );
}
