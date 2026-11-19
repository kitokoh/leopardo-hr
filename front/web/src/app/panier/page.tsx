import type { Metadata } from 'next';

import { normalizeLocale } from '@/lib/i18n';
import { SITE_URL } from '@/lib/site-url';
import { RETAIL_SURFACE, CartSurface, loadCartView } from '@/modules/public-surfaces';

/**
 * Panier public (`/panier`) — #9627.
 *
 * Reprise de `front/marketplace/src/app/panier/page.tsx`.
 *
 * Le panier vit dans `localStorage` : la page est donc rendue côté client pour
 * éviter un flash « panier vide » au premier rendu serveur. Le composant de
 * vitrine reste pur ; c'est cette page qui lit le stockage.
 *
 * `noindex` : un panier n'a aucune valeur d'indexation et exposerait des
 * contenus propres à un visiteur.
 */

export const metadata: Metadata = {
  title: RETAIL_SURFACE.displayName,
  description: RETAIL_SURFACE.tagline,
  alternates: { canonical: `${SITE_URL}/panier` },
  robots: { index: false, follow: false },
};

interface CartPageProps {
  searchParams: Promise<{ lang?: string }>;
}

export default async function CartPage({ searchParams }: CartPageProps) {
  const params = await searchParams;
  const locale = normalizeLocale(params.lang ?? '');

  // `loadCartView` lit `localStorage` : côté serveur il rend un panier vide,
  // que le composant client remplace à l'hydratation.
  const cart = loadCartView(locale);

  return <CartSurface identity={RETAIL_SURFACE} cart={cart} locale={locale} />;
}
