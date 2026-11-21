import type { Metadata } from 'next';

import { TRAVEL_SURFACE, TravelerAccountSurface } from '@/modules/public-surfaces';
import { SurfaceShell } from '@/modules/public-surfaces/kernel/components/SurfaceShell';

export const metadata: Metadata = {
  title: TRAVEL_SURFACE.displayName,
  description: TRAVEL_SURFACE.tagline,
  // Page de compte voyageur : contenu personnel, aucune valeur d'indexation.
  // Sans ceci, Next la laisse indexable par défaut et un crawler peut la
  // classer sur le nom de la verticale.
  robots: { index: false, follow: false },
};

export default function TravelerAccountPage() {
  return (
    <SurfaceShell identity={TRAVEL_SURFACE}>
      <TravelerAccountSurface identity={TRAVEL_SURFACE} />
    </SurfaceShell>
  );
}
