import type { Metadata } from 'next'
import { LegalPageShell } from '@/modules/vitrine/components/LegalPageShell'
import { SITE_URL } from '@/lib/site-url'

export const metadata: Metadata = {
  title: 'Conditions generales d utilisation | Leopardo RH',
  description:
    "Conditions générales d'utilisation multilingues de Leopardo RH pour les clients, administrateurs, managers, employés et intégrateurs.",
  alternates: {
    // #3807 : canonical absolu exigé par Next.js (relatif = canonical invalide).
    canonical: `${SITE_URL}/terms`,
    // #4405 : page non localisée (hors matcher x-vitrine-lang) — on REMPLACE
    // les languages hérités du layout racine (qui pointaient vers la homepage).
    languages: { fr: `${SITE_URL}/terms` },
  },
}

export default function TermsPage() {
  return <LegalPageShell page="terms" />
}
