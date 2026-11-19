import type { Metadata } from 'next';

import { normalizeLocale } from '@/lib/i18n';
import { SITE_URL } from '@/lib/site-url';
import { RETAIL_SURFACE, OrderTrackingSurface, loadTrackingView } from '@/modules/public-surfaces';

/**
 * Suivi de commande public (`/suivi`) — #9627.
 *
 * Reprise de `front/marketplace/src/app/suivi/page.tsx`.
 *
 * Le suivi exige une **référence + un jeton** : sans les deux, la page affiche
 * l'état « indisponible » plutôt qu'un formulaire de recherche. C'est le
 * contrat de l'API (`GET /public/market/orders/{reference}?token=`), et un
 * formulaire qui accepterait une référence seule laisserait croire qu'on peut
 * consulter la commande d'autrui.
 *
 * `noindex` : une page de suivi personnalisée n'a rien à faire dans un index.
 */

export const metadata: Metadata = {
  title: RETAIL_SURFACE.displayName,
  description: RETAIL_SURFACE.tagline,
  alternates: { canonical: `${SITE_URL}/suivi` },
  robots: { index: false, follow: false },
};

interface TrackingPageProps {
  searchParams: Promise<{ ref?: string; token?: string; lang?: string }>;
}

export default async function TrackingPage({ searchParams }: TrackingPageProps) {
  const params = await searchParams;
  const locale = normalizeLocale(params.lang ?? '');

  const reference = (params.ref ?? '').slice(0, 64);
  const token = (params.token ?? '').slice(0, 128);

  const tracking =
    reference !== '' && token !== ''
      ? await loadTrackingView(reference, token, locale)
      : null;

  return <OrderTrackingSurface identity={RETAIL_SURFACE} tracking={tracking} locale={locale} />;
}
