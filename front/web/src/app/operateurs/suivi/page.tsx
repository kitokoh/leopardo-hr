import type { Metadata } from 'next';

import { normalizeLocale } from '@/lib/i18n';
import { RideTrackingSurface, MOBILITY_SURFACE } from '@/modules/public-surfaces';
import { SurfaceShell } from '@/modules/public-surfaces/kernel/components/SurfaceShell';

export const metadata: Metadata = {
  title: 'Suivre ma course — Mobilité',
  description: 'Suivez votre course avec sa référence et son code.',
};

export default async function RideTrackingPage({
  searchParams,
}: {
  searchParams: Promise<{ lang?: string }>;
}) {
  const { lang } = await searchParams;
  const locale = normalizeLocale(lang);

  return (
    <SurfaceShell identity={MOBILITY_SURFACE}>
      <RideTrackingSurface identity={MOBILITY_SURFACE} />
    </SurfaceShell>
  );
}
