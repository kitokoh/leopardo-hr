import type { Metadata } from 'next';

import { normalizeLocale } from '@/lib/i18n';
import { BookingTrackingSurface, TRAVEL_SURFACE } from '@/modules/public-surfaces';
import { SurfaceShell } from '@/modules/public-surfaces/kernel/components/SurfaceShell';

export const metadata: Metadata = {
  title: 'Suivre ma réservation — Billets',
  description: 'Suivez votre réservation avec sa référence et son code.',
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
