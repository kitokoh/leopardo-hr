import type { Metadata } from 'next'
import { LegalPageShell } from '@/modules/vitrine/components/LegalPageShell'
import { SITE_URL } from '@/lib/site-url'

export const metadata: Metadata = {
  title: 'Politique de confidentialite | Leopardo RH',
  description:
    'Politique de confidentialite multilingue de Leopardo RH pour les donnees RH, la conformite, les droits utilisateurs et la securite.',
  alternates: {
    // #3807 : canonical absolu exigé par Next.js (relatif = canonical invalide).
    canonical: `${SITE_URL}/privacy`,
    // #4405 : page non localisée (hors matcher x-vitrine-lang) — on REMPLACE
    // les languages hérités du layout racine (qui pointaient vers la homepage).
    languages: { fr: `${SITE_URL}/privacy` },
  },
}

export default function PrivacyPage() {
  return <LegalPageShell page="privacy" />
}
