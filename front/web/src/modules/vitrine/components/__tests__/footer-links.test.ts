// Jest runtime (next/jest) — globals describe/it/expect fournis par jest
import { getFooterHref } from '../Footer'

describe('getFooterHref', () => {
  it('returns canonical routes for known footer entries', () => {
    expect(getFooterHref(0, 0)).toBe('/#fonctionnalites')
    expect(getFooterHref(1, 4)).toBe('/contact?topic=community')
  })

  it('links previously-orphan pages from the footer (#8075)', () => {
    expect(getFooterHref(0, 8)).toBe('/employes')
    expect(getFooterHref(0, 9)).toBe('/comptabilite')
    expect(getFooterHref(0, 10)).toBe('/marketing')
    expect(getFooterHref(1, 5)).toBe('/case-studies')
    expect(getFooterHref(1, 6)).toBe('/testimonials')
    expect(getFooterHref(1, 7)).toBe('/alternatives')
    expect(getFooterHref(1, 8)).toBe('/restaurateur')
    expect(getFooterHref(1, 9)).toBe('/careers')
    expect(getFooterHref(1, 10)).toBe('/branding')
  })

  it('does not create a silent dead-link fallback', () => {
    expect(getFooterHref(99, 99)).toBeNull()
  })
})
