import type { Metadata } from 'next';

import { normalizeLocale } from '@/lib/i18n';
import { BookingTrackingSurface, TRAVEL_SURFACE } from '@/modules/public-surfaces';
import { SurfaceShell } from '@/modules/public-surfaces/kernel/components/SurfaceShell';

export const metadata: Metadata = {
  title: TRAVEL_SURFACE.displayName,
  description: TRAVEL_SURFACE.tagline,
};

export default async function BookingTrackingPage({
  searchParams,
}: {
  searchParams: Promise<{ lang?: string }>;
}) {
  const { lang } = await searchParams;
  const locale = normalizeLocale(lang);

  return (
    <SurfaceShell identity={TRAVEL_SURFACE}>
      <BookingTrackingSurface identity={TRAVEL_SURFACE} />
    </SurfaceShell>
  );
}
