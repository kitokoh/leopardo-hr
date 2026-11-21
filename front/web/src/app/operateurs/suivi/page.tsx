import type { Metadata } from 'next';

import { normalizeLocale } from '@/lib/i18n';
import { RideTrackingSurface, MOBILITY_SURFACE } from '@/modules/public-surfaces';
import { SurfaceShell } from '@/modules/public-surfaces/kernel/components/SurfaceShell';

export const metadata: Metadata = {
  title: MOBILITY_SURFACE.displayName,
  description: MOBILITY_SURFACE.tagline,
  // Suivi de course par référence : page de consultation privée.
  robots: { index: false, follow: false },
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
