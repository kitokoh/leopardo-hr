import type { Metadata } from 'next';

import { TRAVEL_SURFACE, TravelerAccountSurface } from '@/modules/public-surfaces';
import { SurfaceShell } from '@/modules/public-surfaces/kernel/components/SurfaceShell';

export const metadata: Metadata = {
  title: 'Mon compte — Billets',
  description: 'Vos réservations, toutes agences confondues.',
};

export default function TravelerAccountPage() {
  return (
    <SurfaceShell identity={TRAVEL_SURFACE}>
      <TravelerAccountSurface identity={TRAVEL_SURFACE} />
    </SurfaceShell>
  );
}
