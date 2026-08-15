import { withLocaleHref } from '../Navbar'

describe('withLocaleHref (issue #3806)', () => {
  it('propagates ?lang= when the locale is carried by the URL', () => {
    expect(withLocaleHref('/pricing', 'en', true)).toBe('/pricing?lang=en')
  })

  it('leaves href unchanged when no ?lang= in the URL (localStorage-only locale)', () => {
    expect(withLocaleHref('/pricing', 'en', false)).toBe('/pricing')
    expect(withLocaleHref('/about', 'ar', false)).toBe('/about')
  })

  it('preserves existing query parameters', () => {
    expect(withLocaleHref('/contact?topic=community', 'tr', true)).toBe('/contact?topic=community&lang=tr')
    expect(withLocaleHref('/download?platform=android', 'ar', true)).toBe('/download?platform=android&lang=ar')
  })

  it('preserves anchors', () => {
    expect(withLocaleHref('/#fonctionnalites', 'en', true)).toBe('/?lang=en#fonctionnalites')
    expect(withLocaleHref('/integrations#api', 'fr', true)).toBe('/integrations?lang=fr#api')
  })

  it('leaves external links untouched', () => {
    expect(withLocaleHref('https://github.com/kitokoh/leopardo-hr', 'en', true)).toBe('https://github.com/kitokoh/leopardo-hr')
    expect(withLocaleHref('mailto:contact@leopardo-rh.com', 'en', true)).toBe('mailto:contact@leopardo-rh.com')
  })

  it('replaces an existing lang param on the target', () => {
    expect(withLocaleHref('/pricing?lang=fr', 'ar', true)).toBe('/pricing?lang=ar')
  })
})
