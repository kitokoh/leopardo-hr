import type { Metadata } from 'next';

import { normalizeLocale } from '@/lib/i18n';
import { BuyerOrdersSurface, RETAIL_SURFACE } from '@/modules/public-surfaces';
import { SurfaceShell } from '@/modules/public-surfaces/kernel/components/SurfaceShell';

export const metadata: Metadata = {
  title: RETAIL_SURFACE.displayName,
  description: RETAIL_SURFACE.tagline,
};

export default async function AccountOrdersPage({
  searchParams,
}: {
  searchParams: Promise<{ lang?: string }>;
}) {
  const { lang } = await searchParams;
  const locale = normalizeLocale(lang);

  return (
    <SurfaceShell identity={RETAIL_SURFACE}>
      <BuyerOrdersSurface identity={RETAIL_SURFACE} />
    </SurfaceShell>
  );
}
