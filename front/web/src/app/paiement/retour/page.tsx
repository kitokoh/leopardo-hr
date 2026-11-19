import type { Metadata } from 'next';

import { PaymentReturnSurface, RETAIL_SURFACE } from '@/modules/public-surfaces';
import { SurfaceShell } from '@/modules/public-surfaces/kernel/components/SurfaceShell';

export const metadata: Metadata = {
  title: RETAIL_SURFACE.displayName,
  description: RETAIL_SURFACE.tagline,
};

export default async function PaymentReturnPage({
  searchParams,
}: {
  searchParams: Promise<{ reference?: string }>;
}) {
  const { reference } = await searchParams;

  return (
    <SurfaceShell identity={RETAIL_SURFACE}>
      <PaymentReturnSurface identity={RETAIL_SURFACE} reference={reference ?? ''} />
    </SurfaceShell>
  );
}
