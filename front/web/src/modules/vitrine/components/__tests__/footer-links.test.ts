// Jest runtime (next/jest) — globals describe/it/expect fournis par jest
import { getFooterHref } from '../Footer'

describe('getFooterHref', () => {
  it('returns canonical routes for known footer entries', () => {
    expect(getFooterHref(0, 0)).toBe('/#fonctionnalites')
    expect(getFooterHref(1, 8)).toBe('/business/contact?topic=community')
    expect(getFooterHref(1, 7)).toBe('/toolbox')
    // LEOPARDO LIBRARY — bibliothèque numérique publique
    expect(getFooterHref(1, 9)).toBe('/library')
  })

  it('links previously-orphan pages from the footer (#8075)', () => {
    // Section 0 — Produit & modules
    expect(getFooterHref(0, 7)).toBe('/business/employes')
    expect(getFooterHref(0, 8)).toBe('/business/comptabilite')
    expect(getFooterHref(0, 9)).toBe('/business/marketing')
    expect(getFooterHref(0, 10)).toBe('/decouvrir')

    // Section 1 — Ressources
    expect(getFooterHref(1, 3)).toBe('/business/case-studies')
    expect(getFooterHref(1, 4)).toBe('/business/testimonials')
    expect(getFooterHref(1, 5)).toBe('/business/alternatives')
    expect(getFooterHref(1, 6)).toBe('/business/packs')

    // Section 2 — Entreprise
    expect(getFooterHref(2, 0)).toBe('/business/about')
    expect(getFooterHref(2, 1)).toBe('/business/contact')
    expect(getFooterHref(2, 2)).toBe('/business/careers')
    expect(getFooterHref(2, 3)).toBe('/business/branding')
    expect(getFooterHref(2, 4)).toBe('/business/videos')
    expect(getFooterHref(2, 5)).toBe('/business/investir')
  })

  // #8440 — « Devenir actionnaire » : entrée dans la colonne Entreprise
  // vers la page de manifestation d'intérêt pour prendre des parts.
  it('links the shareholder page from the enterprise column (#8440)', () => {
    expect(getFooterHref(2, 5)).toBe('/business/investir')
  })

  it('does not create a silent dead-link fallback', () => {
    expect(getFooterHref(99, 99)).toBeNull()
  })
})
