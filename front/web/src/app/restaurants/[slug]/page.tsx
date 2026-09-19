import type { Metadata } from 'next';
import { notFound } from 'next/navigation';
import { MapPin, Star } from 'lucide-react';
import { normalizeLocale } from '@/lib/i18n';
import { catalogDirection, t } from '@/lib/i18n/locale-catalog';
import { SITE_URL } from '@/lib/site-url';
import { JsonLd } from '@/components/JsonLd';
import {
  getPublicRestaurant,
  getPublicRestaurantReviews,
  type PublicRestaurantProfile,
} from '@/lib/restaurants-public-api';
import { dayOfWeekName, establishmentTypeLabel, formatRating } from '../format';
import RestaurantOrderPanel from './restaurant-order-panel';

/**
 * RESTO-903 (#7748) — page publique SEO d'un restaurant (`/restaurants/{slug}`).
 *
 * SSR indexable (modèle : `/vitrine/{slug}` #6862) : metadata dynamique +
 * JSON-LD schema.org `Restaurant`, profil (cover, type, cuisines, horaires,
 * note agrégée) rendus côté serveur via `GET /public/restaurants/{slug}`
 * (RESTO-901, 404 fail-closed → notFound()). Le parcours transactionnel
 * (panier → commande idempotente → paiement → suivi → avis, RESTO-902) est
 * porté par le composant client `RestaurantOrderPanel`.
 */

interface RestaurantPageProps {
  params: Promise<{ slug: string }>;
  searchParams: Promise<{ lang?: string }>;
}

export async function generateMetadata({ params, searchParams }: RestaurantPageProps): Promise<Metadata> {
  const { slug } = await params;
  const { lang } = await searchParams;
  const locale = normalizeLocale(lang ?? '');
  const restaurant = await getPublicRestaurant(slug);

  if (!restaurant) {
    return { title: t(locale, 'restaurant.public.notFoundTitle') };
  }

  const title = restaurant.city ? `${restaurant.name} — ${restaurant.city}` : restaurant.name;
  const description =
    restaurant.description ?? t(locale, 'restaurant.public.directoryDescription');

  return {
    title,
    description,
    alternates: { canonical: `${SITE_URL}/restaurants/${restaurant.slug}` },
    robots: { index: true, follow: true },
    openGraph: {
      title,
      description,
      images: restaurant.cover_image_url ? [restaurant.cover_image_url] : undefined,
      type: 'website',
    },
  };
}

function restaurantJsonLd(restaurant: PublicRestaurantProfile): Record<string, unknown> {
  const data: Record<string, unknown> = {
    '@context': 'https://schema.org',
    '@type': 'Restaurant',
    name: restaurant.name,
    url: `${SITE_URL}/restaurants/${restaurant.slug}`,
  };

  if (restaurant.description) {
    data.description = restaurant.description;
  }
  if (restaurant.cover_image_url) {
    data.image = restaurant.cover_image_url;
  }
  if (restaurant.cuisine_types && restaurant.cuisine_types.length > 0) {
    data.servesCuisine = restaurant.cuisine_types;
  }
  if (restaurant.phone) {
    data.telephone = restaurant.phone;
  }
  if (restaurant.address || restaurant.city) {
    data.address = {
      '@type': 'PostalAddress',
      ...(restaurant.address ? { streetAddress: restaurant.address } : {}),
      ...(restaurant.city ? { addressLocality: restaurant.city } : {}),
    };
  }
  if (restaurant.latitude !== null && restaurant.longitude !== null) {
    data.geo = {
      '@type': 'GeoCoordinates',
      latitude: restaurant.latitude,
      longitude: restaurant.longitude,
    };
  }
  if (restaurant.rating_avg !== null && restaurant.reviews_count > 0) {
    data.aggregateRating = {
      '@type': 'AggregateRating',
      ratingValue: restaurant.rating_avg,
      reviewCount: restaurant.reviews_count,
      bestRating: 5,
      worstRating: 1,
    };
  }

  // Convention RestaurantHour (RESTO-805) : day_of_week 0 = lundi … 6 = dimanche.
  const dayNames = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
  const openingHours = restaurant.hours
    .filter((hour) => !hour.is_closed && hour.opens_at && hour.closes_at)
    .map((hour) => ({
      '@type': 'OpeningHoursSpecification',
      dayOfWeek: dayNames[((hour.day_of_week % 7) + 7) % 7],
      opens: hour.opens_at,
      closes: hour.closes_at,
    }));
  if (openingHours.length > 0) {
    data.openingHoursSpecification = openingHours;
  }

  return data;
}

export default async function RestaurantPage({ params, searchParams }: RestaurantPageProps) {
  const { slug } = await params;
  const { lang } = await searchParams;
  const locale = normalizeLocale(lang ?? '');
  const dir = catalogDirection(locale);

  const restaurant = await getPublicRestaurant(slug);

  if (!restaurant) {
    notFound();
  }

  const reviews = await getPublicRestaurantReviews(slug);
  const rating = formatRating(locale, restaurant.rating_avg);
  const typeLabel = establishmentTypeLabel(locale, restaurant.establishment_type);

  return (
    <main
      dir={dir}
      className="min-h-screen bg-gradient-to-br from-slate-50 via-amber-50/40 to-orange-50/40 px-4 py-10"
    >
      <JsonLd data={restaurantJsonLd(restaurant)} />
      <div className="mx-auto max-w-5xl space-y-8">
        {/* Profil public */}
        <header className="overflow-hidden rounded-3xl border border-white/40 bg-white/80 shadow-sm backdrop-blur-xl">
          {restaurant.cover_image_url ? (
            // eslint-disable-next-line @next/next/no-img-element -- URL publique fournie par l'API, dimensions inconnues
            <img
              src={restaurant.cover_image_url}
              alt={restaurant.name}
              className="h-52 w-full object-cover sm:h-64"
            />
          ) : null}
          <div className="space-y-3 p-6">
            <div className="flex flex-wrap items-center gap-3">
              <h1 className="text-3xl font-black tracking-tight text-slate-950">{restaurant.name}</h1>
              {rating ? (
                <span className="inline-flex items-center gap-1 rounded-xl bg-amber-50 px-3 py-1 text-sm font-black text-amber-700">
                  <Star className="h-4 w-4" aria-hidden="true" />
                  {rating}
                  <span className="font-medium text-amber-600">
                    ({restaurant.reviews_count} {t(locale, 'restaurant.public.reviewsSuffix')})
                  </span>
                </span>
              ) : null}
            </div>
            <p className="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-slate-500">
              {typeLabel ? <span className="font-bold text-amber-700">{typeLabel}</span> : null}
              {restaurant.cuisine_types && restaurant.cuisine_types.length > 0 ? (
                <span>
                  {t(locale, 'restaurant.public.cuisinesLabel')} : {restaurant.cuisine_types.join(', ')}
                </span>
              ) : null}
            </p>
            {restaurant.address || restaurant.city ? (
              <p className="flex items-center gap-1.5 text-sm text-slate-600">
                <MapPin className="h-4 w-4 text-amber-600" aria-hidden="true" />
                {[restaurant.address, restaurant.city].filter(Boolean).join(', ')}
              </p>
            ) : null}
            {restaurant.description ? (
              <section aria-label={t(locale, 'restaurant.public.aboutTitle')}>
                <p className="max-w-3xl text-sm text-slate-600">{restaurant.description}</p>
              </section>
            ) : null}
          </div>
        </header>

        {/* Horaires */}
        {restaurant.hours.length > 0 ? (
          <section className="rounded-3xl border border-white/40 bg-white/80 p-6 shadow-sm backdrop-blur-xl">
            <h2 className="mb-3 text-lg font-black tracking-tight text-slate-950">
              {t(locale, 'restaurant.public.hoursTitle')}
            </h2>
            <dl className="grid gap-x-8 gap-y-1 text-sm sm:grid-cols-2">
              {restaurant.hours.map((hour) => (
                <div key={hour.day_of_week} className="flex justify-between gap-4">
                  <dt className="font-bold capitalize text-slate-700">
                    {dayOfWeekName(locale, hour.day_of_week)}
                  </dt>
                  <dd className="text-slate-500">
                    {hour.is_closed || !hour.opens_at || !hour.closes_at
                      ? t(locale, 'restaurant.public.closedDay')
                      : `${hour.opens_at} – ${hour.closes_at}`}
                  </dd>
                </div>
              ))}
            </dl>
          </section>
        ) : null}

        {/* Menu + panier + commande + suivi + avis (client) */}
        <RestaurantOrderPanel
          locale={locale}
          slug={restaurant.slug}
          menu={restaurant.menu}
          initialReviews={reviews?.items ?? []}
          initialReviewsMeta={reviews?.meta ?? null}
        />
      </div>
    </main>
  );
}
