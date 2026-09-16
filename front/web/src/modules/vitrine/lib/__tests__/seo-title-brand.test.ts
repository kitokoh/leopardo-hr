import { generateMetadata, pageMetadata, pageMetadataI18n } from '../seo'

/**
 * Invariant #4612 : « les titres de page n'embarquent plus la marque (retirée
 * des catalogues) ; le template la pose dans la langue de la page ».
 *
 * Défaut mesuré sur le HTML servi (2026-09-16) : des titres portaient encore la
 * marque, que le gabarit racine ajoutait une seconde fois —
 * « Questions Fréquentes | FAQ Leopardo RH | Leopardo RH ». Le balayage de
 * #4612 était incomplet ; ce test empêche qu'il le redevienne.
 *
 * (Jest n'accepte pas de message en 2e argument de `expect` — on collecte donc
 * les violations pour les rendre lisibles dans l'assertion.)
 */
const BRANDS = ['leopardo', 'ليوباردو']

function titlesContainingBrand(entries: Record<string, { title: string }>, scope: string): string[] {
  const offenders: string[] = []
  for (const [key, entry] of Object.entries(entries)) {
    for (const brand of BRANDS) {
      if (entry.title.toLowerCase().includes(brand)) {
        offenders.push(`${scope}/${key} : « ${entry.title} »`)
      }
    }
  }
  return offenders
}

describe('titres du catalogue — aucune marque (invariant #4612)', () => {
  it('aucun titre FR du catalogue ne contient la marque', () => {
    expect(titlesContainingBrand(pageMetadata, 'fr')).toEqual([])
  })

  it('aucun titre localisé (en/tr/ar) ne contient la marque', () => {
    const offenders = Object.entries(pageMetadataI18n).flatMap(([locale, entries]) =>
      titlesContainingBrand(entries as Record<string, { title: string }>, locale),
    )
    expect(offenders).toEqual([])
  })

  it('le titre reste celui du catalogue — le gabarit ajoute la marque une seule fois', () => {
    const meta = generateMetadata({
      title: 'Questions fréquentes | FAQ',
      description: 'd',
      canonical: '/faq',
    })
    expect(meta.title).toBe('Questions fréquentes | FAQ')
  })
})
