import type { Metadata } from 'next'
import { LegalPageShell } from '@/modules/vitrine/components/LegalPageShell'
import { SITE_URL } from '@/lib/site-url'

export const metadata: Metadata = {
  title: 'Conditions generales d utilisation | Leopardo RH',
  description:
    'Conditions generales d utilisation multilingues de Leopardo RH pour les clients, administrateurs, managers, employes et integrateurs.',
  alternates: {
    // #3807 : canonical absolu exigé par Next.js (relatif = canonical invalide).
    canonical: `${SITE_URL}/terms`,
  },
}

export default function TermsPage() {
  return <LegalPageShell page="terms" />
}
