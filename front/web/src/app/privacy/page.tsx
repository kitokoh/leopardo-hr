import type { Metadata } from 'next'
import { LegalPageShell } from '@/modules/vitrine/components/LegalPageShell'
import { SITE_URL } from '@/lib/site-url'
import { legalPageSeo } from '@/modules/vitrine/data/legal-seo'

export const metadata: Metadata = {
  title: legalPageSeo.privacy.title,
  description: legalPageSeo.privacy.description,
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
