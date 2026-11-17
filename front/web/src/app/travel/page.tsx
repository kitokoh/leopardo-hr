import type { Metadata } from 'next';

import { normalizeLocale } from '@/lib/i18n';
import { SITE_URL } from '@/lib/site-url';
import { TRAVEL_SURFACE, TravelSurface, loadTravelSurface } from '@/modules/public-surfaces';

/**
 * Vitrine publique Billets — annuaire des trajets (`/travel`), #9593.
 *
 * SSR indexable, alimentée par `GET /api/v1/public/travel/marketplace/trips`
 * (throttle `shop-public`, aucune auth).
 *
 * ## Pourquoi cette page n'existait pas
 *
 * La verticale Billets avait son identité, son domaine, sa gateway (`trip-gateway`)
 * et son composant de vitrine — mais **aucune route d'index**. Un visiteur ne
 * pouvait donc pas parcourir la verticale, et `/travel/contact` était la seule
 * page publique du segment : les trajets n'étaient atteignables que par lien
 * direct. Même trou que `/boutiques` avant P1-02.
 *
 * ## Ce que la page ne fait pas
 *
 * Elle ne réimplémente ni la réservation ni le paiement : la vitrine présente,
 * les parcours existants vendent (`/public/travel/marketplace/bookings`).
 */

interface TravelPageProps {
  searchParams: Promise<{ origin?: string; destination?: string; page?: string; lang?: string }>;
}

export async function generateMetadata({ searchParams }: TravelPageProps): Promise<Metadata> {
  const { lang } = await searchParams;
  const locale = normalizeLocale(lang ?? '');

  return {
    title: TRAVEL_SURFACE.displayName,
    description: TRAVEL_SURFACE.tagline,
    alternates: { canonical: `${SITE_URL}${TRAVEL_SURFACE.basePath}` },
    robots: { index: true, follow: true },
    openGraph: {
      title: TRAVEL_SURFACE.displayName,
      description: TRAVEL_SURFACE.tagline,
      type: 'website',
    },
  };
}

export default async function TravelPage({ searchParams }: TravelPageProps) {
  const params = await searchParams;
  const locale = normalizeLocale(params.lang ?? '');

  const result = await loadTravelSurface({
    origin: (params.origin ?? '').slice(0, 120) || undefined,
    destination: (params.destination ?? '').slice(0, 120) || undefined,
    page: Math.max(1, Number.parseInt(params.page ?? '1', 10) || 1),
    locale,
  });

  return (
    <TravelSurface
      identity={TRAVEL_SURFACE}
      cards={result.cards}
      failed={result.failed}
      locale={locale}
    />
  );
}
