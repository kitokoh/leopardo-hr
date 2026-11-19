import type { Metadata } from 'next';

import { normalizeLocale } from '@/lib/i18n';
import { BuyerFavoritesSurface, RETAIL_SURFACE } from '@/modules/public-surfaces';
import { SurfaceShell } from '@/modules/public-surfaces/kernel/components/SurfaceShell';

export const metadata: Metadata = {
  title: 'Mes favoris — Marché',
  description: 'Les produits que vous avez mis de côté.',
};

export default async function AccountFavoritesPage({
  searchParams,
}: {
  searchParams: Promise<{ lang?: string }>;
}) {
  const { lang } = await searchParams;
  const locale = normalizeLocale(lang);

  return (
    <SurfaceShell identity={RETAIL_SURFACE}>
      <BuyerFavoritesSurface identity={RETAIL_SURFACE} />
    </SurfaceShell>
  );
}
