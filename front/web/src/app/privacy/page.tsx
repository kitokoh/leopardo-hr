import type { Metadata } from 'next'
import { LegalPageShell } from '@/modules/vitrine/components/LegalPageShell'

export const metadata: Metadata = {
  title: 'Politique de confidentialite | Leopardo RH',
  description:
    'Politique de confidentialite multilingue de Leopardo RH pour les donnees RH, la conformite, les droits utilisateurs et la securite.',
  alternates: {
    canonical: '/privacy',
  },
}

export default function PrivacyPage() {
  return <LegalPageShell page="privacy" />
}
