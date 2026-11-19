import type { Metadata } from 'next';

import { normalizeLocale } from '@/lib/i18n';
import { CheckoutSurface, RETAIL_SURFACE, buildCartView } from '@/modules/public-surfaces';
import { SurfaceShell } from '@/modules/public-surfaces/kernel/components/SurfaceShell';

export const metadata: Metadata = {
  title: RETAIL_SURFACE.displayName,
  description: RETAIL_SURFACE.tagline,
};

export default async function CheckoutPage({
  searchParams,
}: {
  searchParams: Promise<{ lang?: string }>;
}) {
  const { lang } = await searchParams;
  const locale = normalizeLocale(lang);

  return (
    <SurfaceShell identity={RETAIL_SURFACE}>
      <CheckoutSurface identity={RETAIL_SURFACE} initialCart={buildCartView([])} />
    </SurfaceShell>
  );
}
