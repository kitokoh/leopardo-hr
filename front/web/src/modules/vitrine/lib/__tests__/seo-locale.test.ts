import { getPageMetadata, pageMetadata, pageMetadataI18n } from '../seo'

describe('getPageMetadata (#4004 — SEO localisé)', () => {
  it('returns the FR default when no lang is provided', () => {
    expect(getPageMetadata('demo').title).toBe(pageMetadata.demo.title)
    expect(getPageMetadata('demo', 'fr').description).toBe(pageMetadata.demo.description)
  })

  it('returns EN title/description for ?lang=en', () => {
    const en = getPageMetadata('demo', 'en')
    expect(en.title).toBe(pageMetadataI18n.en.demo.title)
    expect(en.title).toMatch(/demo/i)
    expect(en.description).toBe(pageMetadataI18n.en.demo.description)
  })

  it('falls back to FR for unknown pages or locales', () => {
    expect(getPageMetadata('does-not-exist', 'en').title).toBe(pageMetadata.landing.title)
    expect(getPageMetadata('demo', 'xx').title).toBe(pageMetadata.demo.title)
  })

  it('keeps keywords/ogImage from the FR base for overridden locales', () => {
    const tr = getPageMetadata('pricing', 'tr')
    expect(tr.title).toBe(pageMetadataI18n.tr.pricing.title)
    expect(tr.keywords).toEqual(pageMetadata.pricing.keywords)
    expect(tr.ogImage).toBe(pageMetadata.pricing.ogImage)
  })

  it('covers all pages in EN/TR/AR with non-empty title and description', () => {
    const pages = Object.keys(pageMetadata).filter((k) => k !== 'landing')
    expect(pages.length).toBeGreaterThanOrEqual(26)
    // #4612 : le titre catalogue peut être court (« Changelog ») — la marque
    // locale est apposée par le template du layout racine (PR #4755). Le garde
    // porte sur le titre FINAL (catalogue + marque), comme rendu en production.
    const brandFor: Record<string, string> = { en: 'Leopardo HR', tr: 'Leopardo İK', ar: 'ليوباردو' }
    for (const locale of ['en', 'tr', 'ar'] as const) {
      for (const page of [...pages, 'landing']) {
        const seo = getPageMetadata(page, locale)
        const finalTitle = `${seo.title} | ${brandFor[locale]}`
        expect(finalTitle.length).toBeGreaterThan(10)
        expect(finalTitle.length).toBeLessThanOrEqual(80)
        expect(seo.description.length).toBeGreaterThan(30)
      }
    }
  })

  /**
   * #AI-SEO (audit 2026-09-13) : garde de longueur des titres.
   *
   * Avant cet audit, 10 pages dépassaient 60 caractères dans les SERP
   * (`/documents` 70, `/marketing` 69, `/download` 68, `/branding` 68,
   * `/mobile` 67, `/employes` 65, `/comptabilite` 63, `/docs` 63, `/terms` 62,
   * `/videos` 61) — tronqués par Google, donc mot-clé final invisible.
   *
   * Exclus volontairement :
   *  - `landing` : titre de repli pour une page inconnue, jamais servi ;
   *  - `guides` : l'index /guides n'existe pas (layout sans page → 404) ;
   *  - `signup` / `checkout` / `checkoutSuccess` : noindex, aucun effet SERP.
   *
   * Les 3 pages de guide sont dans un segment imbriqué : le template du layout
   * racine ne leur applique PAS la marque (vérifié en production — #4611/#7192),
   * donc le garde les évalue sans suffixe, comme elles sont réellement servies.
   */
  it('#AI-SEO : les titres indexables tiennent dans 60 caractères, telle que servis', () => {
    const brandFor: Record<string, string> = { fr: 'Leopardo RH', en: 'Leopardo HR', tr: 'Leopardo İK', ar: 'ليوباردو' }
    const excluded = new Set(['landing', 'guides', 'signup', 'checkout', 'checkoutSuccess'])
    // Segment imbriqué : la marque n'est pas ajoutée par le template racine.
    const noBrandSuffix = new Set(['guideRhStartup', 'guidePlanningEmployes', 'guideChecklistPaie'])
    const offenders: string[] = []
    for (const locale of ['fr', 'en', 'tr', 'ar'] as const) {
      for (const page of Object.keys(pageMetadata)) {
        if (excluded.has(page)) continue
        const title = getPageMetadata(page, locale).title
        const finalTitle = noBrandSuffix.has(page) ? title : `${title} | ${brandFor[locale]}`
        if (finalTitle.length > 60) offenders.push(`[${locale}] ${page} (${finalTitle.length})`)
      }
    }
    expect(offenders).toEqual([])
  })
})
