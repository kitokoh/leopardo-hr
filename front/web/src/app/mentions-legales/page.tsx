import type { Metadata } from 'next'

import { LegalPageShell } from '@/modules/vitrine/components/LegalPageShell'
import { legalPageSeo } from '@/modules/vitrine/data/legal-seo'
import { SITE_URL } from '@/lib/site-url'

/**
 * Mentions légales (#7593).
 *
 * Le pied de page portait un lien « Mentions légales » qui pointait vers les
 * CGU (corrigé dans le lot vitrine #7592) : la page devait exister. Même
 * mécanique que `/privacy` et `/terms` (contenu dans `legal-content.ts`, ×4
 * langues), page FR uniquement, hors matcher `x-vitrine-lang`.
 */
export const metadata: Metadata = {
  title: legalPageSeo.legal.title,
  description: legalPageSeo.legal.description,
  alternates: {
    canonical: `${SITE_URL}/mentions-legales`,
    // Page non localisée : on remplace les `languages` hérités du layout racine
    // (qui pointaient vers la homepage) — même correctif que /terms (#4405).
    languages: { fr: `${SITE_URL}/mentions-legales` },
  },
}

export default function LegalNoticePage() {
  return <LegalPageShell page="legal" />
}
