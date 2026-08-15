import { describe, expect, it } from 'vitest'
import { buildLocaleUrl } from '../Navbar'

describe('buildLocaleUrl', () => {
  it('preserves existing query parameters while replacing lang', () => {
    expect(buildLocaleUrl('/pricing', '?plan=pilot&lang=fr', 'ar')).toBe('/pricing?plan=pilot&lang=ar')
  })

  it('adds lang when the current URL has no query string', () => {
    expect(buildLocaleUrl('/guides/rh-startup', '', 'tr')).toBe('/guides/rh-startup?lang=tr')
  })

  it('encodes locale values through URLSearchParams', () => {
    expect(buildLocaleUrl('/faq', '?topic=community', 'en')).toBe('/faq?topic=community&lang=en')
  })
})
