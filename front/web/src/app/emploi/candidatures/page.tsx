import type { Metadata } from 'next';

import { CAREERS_SURFACE, TrackApplicationsForm } from '@/modules/public-surfaces';
import { SurfaceShell } from '@/modules/public-surfaces/kernel/components/SurfaceShell';

export const metadata: Metadata = {
  title: 'Suivre mes candidatures — Emploi',
  description: 'Retrouvez vos candidatures avec l’adresse e-mail utilisée pour postuler.',
};

/**
 * Suivi des candidatures, sans compte (#9629).
 *
 * Le contrat API n'a pas de compte candidat : le suivi se fait par e-mail. On
 * ne simule donc pas un espace candidat qui n'existe pas — on expose le
 * parcours réel.
 */
export default async function TrackApplicationsPage({
  searchParams,
}: {
  searchParams: Promise<{ companySlug?: string }>;
}) {
  const { companySlug } = await searchParams;

  return (
    <SurfaceShell identity={CAREERS_SURFACE}>
      <TrackApplicationsForm
        identity={CAREERS_SURFACE}
        companySlug={companySlug ?? ''}
      />
    </SurfaceShell>
  );
}
