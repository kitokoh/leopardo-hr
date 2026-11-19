import type { Metadata } from 'next';

import { normalizeLocale } from '@/lib/i18n';
import { BuyerAccountSurface, RETAIL_SURFACE } from '@/modules/public-surfaces';
import { SurfaceShell } from '@/modules/public-surfaces/kernel/components/SurfaceShell';

export const metadata: Metadata = {
  title: 'Mon compte — Marché',
  description: 'Connexion, commandes et favoris.',
};

export default async function AccountPage({
  searchParams,
}: {
  searchParams: Promise<{ lang?: string }>;
}) {
  const { lang } = await searchParams;
  const locale = normalizeLocale(lang);

  return (
    <SurfaceShell identity={RETAIL_SURFACE}>
      <BuyerAccountSurface identity={RETAIL_SURFACE} />
    </SurfaceShell>
  );
}
