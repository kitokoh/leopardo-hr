// Jest runtime (next/jest) — globals describe/it/expect fournis par jest
import { getFooterHref } from '../Footer'

describe('getFooterHref', () => {
  it('returns canonical routes for known footer entries', () => {
    expect(getFooterHref(0, 0)).toBe('/#fonctionnalites')
    expect(getFooterHref(1, 8)).toBe('/contact?topic=community')
    expect(getFooterHref(1, 7)).toBe('/toolbox')
  })

  it('links previously-orphan pages from the footer (#8075)', () => {
    // Section 0 — Produit & modules
    expect(getFooterHref(0, 7)).toBe('/employes')
    expect(getFooterHref(0, 8)).toBe('/comptabilite')
    expect(getFooterHref(0, 9)).toBe('/marketing')
    expect(getFooterHref(0, 10)).toBe('/decouvrir')

    // Section 1 — Ressources
    expect(getFooterHref(1, 3)).toBe('/case-studies')
    expect(getFooterHref(1, 4)).toBe('/testimonials')
    expect(getFooterHref(1, 5)).toBe('/alternatives')
    expect(getFooterHref(1, 6)).toBe('/packs')

    // Section 2 — Entreprise
    expect(getFooterHref(2, 0)).toBe('/about')
    expect(getFooterHref(2, 1)).toBe('/contact')
    expect(getFooterHref(2, 2)).toBe('/careers')
    expect(getFooterHref(2, 3)).toBe('/branding')
    expect(getFooterHref(2, 4)).toBe('/videos')
    expect(getFooterHref(2, 5)).toBe('/investir')
  })

  // #8440 — « Devenir actionnaire » : entrée dans la colonne Entreprise
  // vers la page de manifestation d'intérêt pour prendre des parts.
  it('links the shareholder page from the enterprise column (#8440)', () => {
    expect(getFooterHref(2, 5)).toBe('/investir')
  })

  it('does not create a silent dead-link fallback', () => {
    expect(getFooterHref(99, 99)).toBeNull()
  })
})
