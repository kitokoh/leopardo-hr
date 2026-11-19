import type { Metadata } from 'next';

import { TRAVEL_SURFACE, TravelerAccountSurface } from '@/modules/public-surfaces';
import { SurfaceShell } from '@/modules/public-surfaces/kernel/components/SurfaceShell';

export const metadata: Metadata = {
  title: TRAVEL_SURFACE.displayName,
  description: TRAVEL_SURFACE.tagline,
};

export default function TravelerAccountPage() {
  return (
    <SurfaceShell identity={TRAVEL_SURFACE}>
      <TravelerAccountSurface identity={TRAVEL_SURFACE} />
    </SurfaceShell>
  );
}
