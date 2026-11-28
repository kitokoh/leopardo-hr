import type { Metadata } from 'next';
import { notFound } from 'next/navigation';
import { MapPin, Phone, Star } from 'lucide-react';
import { normalizeLocale } from '@/lib/i18n';
import { catalogDirection, t } from '@/lib/i18n/locale-catalog';
import { JsonLd } from '@/components/JsonLd';
import {
  getPublicStayProperty,
  type PublicStayProperty,
} from '@/lib/hospitality-public-api';
import {
  HOSPITALITY_SURFACE,
  StayBookingPanel,
  SurfaceNotice,
  SurfacePagination,
  SurfaceSearchBar,
  filterByQuery,
  formatStayMoney,
  paginateList,
  stayPropertyTypeLabel,
  surfaceUrl,
} from '@/modules/public-surfaces';

/** Types de chambre affichés par page sur la fiche d'un hébergement. */
const ROOM_TYPES_PER_PAGE = 12;

/**
 * HOSP-008 (#7950) — page publique SEO d'un établissement HospitalityManager
 * (`/stay/{slug}`, BC-32).
 *
 * SSR indexable (modèle : `/restaurants/{slug}` RESTO-903 #7748) : metadata
 * dynamique + JSON-LD schema.org `LodgingBusiness`/`Hotel`, fiche (type,
 * adresse, étoiles, équipements, types de chambres et prix) rendue côté
 * serveur via `GET /public/hospitality/properties/{slug}` (HOSP-006,
 * fail-closed : dé-publié ou inconnu → notFound()). Le parcours
 * transactionnel (disponibilités → réservation idempotente `pending`
 * expirant à +30 min → confirmation avec référence + code de suivi →
 * suivi/annulation sans compte) est porté par le composant client
 * `StayBookingPanel`. `/stay` reste HORS `PROTECTED_PREFIXES` (public par
 * nature — commentaire dans `protected-prefixes.ts`, HOSP-001 #7943).
 */

interface StayPageProps {
  params: Promise<{ slug: string }>;
  searchParams: Promise<{ lang?: string; q?: string; page?: string }>;
}

export async function generateMetadata({ params, searchParams }: StayPageProps): Promise<Metadata> {
  const { slug } = await params;
  const { lang } = await searchParams;
  const locale = normalizeLocale(lang ?? '');
  const property = await getPublicStayProperty(slug);

  if (!property) {
    return { title: t(locale, 'stay.public.notFoundTitle') };
  }

  const title = property.city ? `${property.name} — ${property.city}` : property.name;
  const description = t(locale, 'stay.public.metaDescription').replace('{name}', property.name);

  return {
    title,
    description,
    alternates: { canonical: surfaceUrl(HOSPITALITY_SURFACE, `/stay/${property.slug}`) },
    robots: { index: true, follow: true },
    openGraph: { title, description, type: 'website' },
  };
}

/** Mapping type d'établissement → type schema.org le plus précis. */
const SCHEMA_TYPES: Record<string, string> = {
  hotel: 'Hotel',
  guesthouse: 'BedAndBreakfast',
  residence: 'LodgingBusiness',
  apartment_building: 'ApartmentComplex',
};

function stayJsonLd(property: PublicStayProperty): Record<string, unknown> {
  const data: Record<string, unknown> = {
    '@context': 'https://schema.org',
    '@type': SCHEMA_TYPES[property.type] ?? 'LodgingBusiness',
    name: property.name,
    url: surfaceUrl(HOSPITALITY_SURFACE, `/stay/${property.slug}`),
  };

  if (property.phone) {
    data.telephone = property.phone;
  }
  if (property.address || property.city || property.country) {
    data.address = {
      '@type': 'PostalAddress',
      ...(property.address ? { streetAddress: property.address } : {}),
      ...(property.city ? { addressLocality: property.city } : {}),
      ...(property.country ? { addressCountry: property.country } : {}),
    };
  }
  if (property.latitude !== null && property.longitude !== null) {
    data.geo = {
      '@type': 'GeoCoordinates',
      latitude: property.latitude,
      longitude: property.longitude,
    };
  }
  if (property.star_rating !== null && property.star_rating > 0) {
    data.starRating = { '@type': 'Rating', ratingValue: property.star_rating };
  }
  if (property.amenities && property.amenities.length > 0) {
    data.amenityFeature = property.amenities.map((amenity) => ({
      '@type': 'LocationFeatureSpecification',
      name: amenity,
      value: true,
    }));
  }
  if (property.room_types.length > 0) {
    data.makesOffer = property.room_types.map((roomType) => ({
      '@type': 'Offer',
      name: roomType.name,
      price: (roomType.base_price_minor / 100).toFixed(2),
      priceCurrency: roomType.currency,
    }));
  }

  return data;
}

export default async function StayPage({ params, searchParams }: StayPageProps) {
  const { slug } = await params;
  const search = await searchParams;
  const locale = normalizeLocale(search.lang ?? '');
  const dir = catalogDirection(locale);
  const property = await getPublicStayProperty(slug);

  if (!property) {
    notFound();
  }

  const query = (search.q ?? '').slice(0, 120) || undefined;
  const pageNumber = Number.parseInt(search.page ?? '', 10);
  const roomTypes = paginateList(
    filterByQuery(property.room_types, query, (roomType) => [roomType.name, roomType.description]),
    Number.isFinite(pageNumber) ? pageNumber : undefined,
    ROOM_TYPES_PER_PAGE,
  );

  return (
    <div
      dir={dir}
      data-surface={HOSPITALITY_SURFACE.vertical}
      className="mx-auto w-full max-w-4xl px-4 py-10 sm:px-6"
      style={{ backgroundColor: HOSPITALITY_SURFACE.palette.surface }}
    >
      <JsonLd data={stayJsonLd(property)} />

      <header className="border-b border-slate-200 pb-6">
        <p className="text-sm font-medium uppercase tracking-wide text-emerald-700">
          {stayPropertyTypeLabel(locale, property.type)}
        </p>
        <h1 className="mt-1 text-3xl font-bold text-slate-900">{property.name}</h1>
        <div className="mt-3 flex flex-wrap items-center gap-4 text-sm text-slate-600">
          {property.star_rating !== null && property.star_rating > 0 ? (
            <span className="inline-flex items-center gap-1" aria-label={`${property.star_rating} ★`}>
              {Array.from({ length: property.star_rating }, (_, index) => (
                <Star key={index} className="h-4 w-4 fill-amber-400 text-amber-400" aria-hidden />
              ))}
            </span>
          ) : null}
          {property.address || property.city ? (
            <span className="inline-flex items-center gap-1">
              <MapPin className="h-4 w-4" aria-hidden />
              {[property.address, property.city].filter(Boolean).join(', ')}
            </span>
          ) : null}
          {property.phone ? (
            <a href={`tel:${property.phone}`} className="inline-flex items-center gap-1 hover:text-slate-900">
              <Phone className="h-4 w-4" aria-hidden />
              {property.phone}
            </a>
          ) : null}
        </div>
        {property.amenities && property.amenities.length > 0 ? (
          <ul className="mt-4 flex flex-wrap gap-2">
            {property.amenities.map((amenity) => (
              <li
                key={amenity}
                className="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-700"
              >
                {amenity}
              </li>
            ))}
          </ul>
        ) : null}
      </header>

      <section className="mt-8" aria-labelledby="stay-room-types-title">
        <h2 id="stay-room-types-title" className="text-xl font-semibold text-slate-900">
          {t(locale, 'stay.public.roomTypesTitle')}
        </h2>
        {property.room_types.length === 0 ? (
          <p className="mt-3 text-sm text-slate-600">{t(locale, 'stay.public.noRoomTypes')}</p>
        ) : (
          <>
            <div className="mt-4">
              <SurfaceSearchBar
                identity={HOSPITALITY_SURFACE}
                basePath={`/stay/${property.slug}`}
                defaultQuery={query}
                labels={{
                  searchLabel: t(locale, 'discovery.searchLabel'),
                  searchPlaceholder: t(locale, 'discovery.searchPlaceholder'),
                  searchSubmit: t(locale, 'discovery.search'),
                  searchReset: t(locale, 'discovery.clearNear'),
                }}
              />
            </div>

            {roomTypes.items.length === 0 ? (
              <div className="mt-4">
                <SurfaceNotice identity={HOSPITALITY_SURFACE}>
                  {t(locale, 'discovery.noResults')}
                </SurfaceNotice>
              </div>
            ) : (
              <ul className="mt-4 grid gap-4 sm:grid-cols-2">
                {roomTypes.items.map((roomType) => (
                  <li key={roomType.code} className="rounded-lg border border-slate-200 p-4">
                    <p className="font-semibold text-slate-900">{roomType.name}</p>
                    {roomType.description ? (
                      <p className="mt-1 text-sm text-slate-600">{roomType.description}</p>
                    ) : null}
                    <p className="mt-2 text-sm font-medium text-emerald-700">
                      {t(locale, 'stay.public.pricePerNight').replace(
                        '{price}',
                        formatStayMoney(roomType.base_price_minor, roomType.currency, locale),
                      )}
                    </p>
                  </li>
                ))}
              </ul>
            )}

            <SurfacePagination
              identity={HOSPITALITY_SURFACE}
              basePath={`/stay/${property.slug}`}
              page={roomTypes.page}
              lastPage={roomTypes.lastPage}
              searchParams={{ q: query }}
              labels={{
                previous: t(locale, 'discovery.previous'),
                next: t(locale, 'discovery.next'),
                pageOf: `${t(locale, 'discovery.pageLabel')} {page} / {last}`,
              }}
            />
          </>
        )}
      </section>

      <StayBookingPanel
        slug={property.slug}
        locale={locale}
        roomTypes={property.room_types}
      />
    </div>
  );
}
