import { getPageMetadata, pageMetadata, pageMetadataI18n } from '../seo'

describe('getPageMetadata (#4004 — SEO localisé)', () => {
  it('returns the FR default when no lang is provided', () => {
    expect(getPageMetadata('demo').title).toBe(pageMetadata.demo.title)
    expect(getPageMetadata('demo', 'fr').description).toBe(pageMetadata.demo.description)
  })

  it('returns EN title/description for ?lang=en', () => {
    const en = getPageMetadata('demo', 'en')
    expect(en.title).toBe(pageMetadataI18n.en.demo.title)
    expect(en.title).toContain('Demo')
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

  it('covers all 27 pages in EN/TR/AR with non-empty title and description', () => {
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
})
