import type { Metadata } from 'next'
import { LegalPageShell } from '@/modules/vitrine/components/LegalPageShell'

export const metadata: Metadata = {
  title: 'Conditions generales d utilisation | Leopardo RH',
  description:
    'Conditions generales d utilisation multilingues de Leopardo RH pour les clients, administrateurs, managers, employes et integrateurs.',
  alternates: {
    canonical: '/terms',
  },
}

export default function TermsPage() {
  return <LegalPageShell page="terms" />
}
