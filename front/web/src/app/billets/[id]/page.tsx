import type { Metadata } from 'next';
import { notFound } from 'next/navigation';

import { normalizeLocale } from '@/lib/i18n';
import { JsonLd } from '@/components/JsonLd';
import { TRAVEL_SURFACE, TripDetailSurface, loadTripDetailView,
  surfaceUrl,
} from '@/modules/public-surfaces';

/**
 * Fiche trajet publique (`/billets/{id}`) — #9628.
 *
 * Reprise de `front/travel-web/src/app/trips/[id]/page.tsx` lors de
 * l'absorption de l'app autonome. Le plan de sièges, la résolution de
 * géométrie et la légende accessibles vivent dans la verticale.
 *
 * ## 404 vs panne
 *
 * `loadTripDetailView` rend `null` dans les deux cas, et `notFound()` est le
 * comportement correct pour les deux : un trajet qu'on ne peut pas charger
 * n'est pas réservable.
 */

interface TripPageProps {
  params: Promise<{ id: string }>;
  searchParams: Promise<{ lang?: string }>;
}

export async function generateMetadata({ params, searchParams }: TripPageProps): Promise<Metadata> {
  const { id } = await params;
  const { lang } = await searchParams;
  const locale = normalizeLocale(lang ?? '');
  const view = await loadTripDetailView(id, locale);

  if (view === null) {
    return { title: TRAVEL_SURFACE.displayName, robots: { index: false, follow: false } };
  }

  const title = `${view.route} — ${TRAVEL_SURFACE.displayName}`;

  return {
    title,
    description: TRAVEL_SURFACE.tagline,
    alternates: { canonical: surfaceUrl(TRAVEL_SURFACE, `/billets/${id}`) },
    robots: { index: true, follow: true },
    openGraph: { title, description: TRAVEL_SURFACE.tagline, type: 'website' },
  };
}

export default async function TripPage({ params, searchParams }: TripPageProps) {
  const { id } = await params;
  const { lang } = await searchParams;
  const locale = normalizeLocale(lang ?? '');

  const view = await loadTripDetailView(id, locale);

  if (view === null) {
    notFound();
  }

  const jsonLd: Record<string, unknown> = {
    '@context': 'https://schema.org',
    '@type': 'BusTrip',
    name: view.route,
    url: surfaceUrl(TRAVEL_SURFACE, `/billets/${id}`),
    ...(view.trip.departureAt ? { departureTime: view.trip.departureAt } : {}),
    ...(view.trip.arrivalAt ? { arrivalTime: view.trip.arrivalAt } : {}),
    provider: { '@type': 'Organization', name: TRAVEL_SURFACE.displayName },
    ...(view.priceLabel !== null && view.trip.priceMinor !== null
      ? {
          offers: {
            '@type': 'Offer',
            price: (view.trip.priceMinor / 100).toFixed(2),
            priceCurrency: view.trip.currency ?? 'EUR',
            availability: view.bookable
              ? 'https://schema.org/InStock'
              : 'https://schema.org/SoldOut',
          },
        }
      : {}),
  };

  return (
    <>
      <JsonLd data={jsonLd} />
      <TripDetailSurface identity={TRAVEL_SURFACE} view={view} locale={locale} />
    </>
  );
}
