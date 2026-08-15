// Jest runtime (next/jest) — globals describe/it/expect fournis par jest
import { getFooterHref } from '../Footer'

describe('getFooterHref', () => {
  it('returns canonical routes for known footer entries', () => {
    expect(getFooterHref(0, 0)).toBe('/#fonctionnalites')
    expect(getFooterHref(1, 4)).toBe('/contact?topic=community')
  })

  it('does not create a silent dead-link fallback', () => {
    expect(getFooterHref(99, 99)).toBeNull()
  })
})
