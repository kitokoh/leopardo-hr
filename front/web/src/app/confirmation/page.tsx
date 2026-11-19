import type { Metadata } from 'next';

import { normalizeLocale } from '@/lib/i18n';
import { OrderConfirmationSurface, RETAIL_SURFACE } from '@/modules/public-surfaces';
import { SurfaceShell } from '@/modules/public-surfaces/kernel/components/SurfaceShell';

export const metadata: Metadata = {
  title: 'Commande confirmée — Marché',
  description: 'Vos commandes enregistrées et leurs références de suivi.',
};

export default async function ConfirmationPage({
  searchParams,
}: {
  searchParams: Promise<{ lang?: string }>;
}) {
  const { lang } = await searchParams;
  const locale = normalizeLocale(lang);

  return (
    <SurfaceShell identity={RETAIL_SURFACE}>
      <OrderConfirmationSurface identity={RETAIL_SURFACE} />
    </SurfaceShell>
  );
}
