/**
 * Regression guard #3806 — la locale `?lang=` doit survivre aux liens internes
 * Navbar/Footer (résiduel de #3735 qui ne couvrait que le <select>).
 */
import { withLocaleHref } from '../locale-href'

describe('withLocaleHref (#3806)', () => {
  it('ajoute lang quand la recherche courante en porte un', () => {
    expect(withLocaleHref('/pricing', 'lang=en')).toBe('/pricing?lang=en')
  })

  it("n'ajoute rien quand la recherche courante n'a pas de lang", () => {
    expect(withLocaleHref('/pricing', '')).toBe('/pricing')
    expect(withLocaleHref('/pricing', 'utm_source=x')).toBe('/pricing')
  })

  it('préserve les query params existants de la cible', () => {
    expect(withLocaleHref('/contact?topic=community', 'lang=ar')).toBe('/contact?topic=community&lang=ar')
  })

  it('préserve les ancres', () => {
    expect(withLocaleHref('/#fonctionnalites', 'lang=en')).toBe('/?lang=en#fonctionnalites')
    expect(withLocaleHref('/download#mobile-apps', 'lang=tr')).toBe('/download?lang=tr#mobile-apps')
  })

  it('remplace un lang existant sur la cible', () => {
    expect(withLocaleHref('/pricing?lang=fr', 'lang=en')).toBe('/pricing?lang=en')
  })

  it('laisse les liens externes et ancres seules intacts', () => {
    expect(withLocaleHref('https://github.com/kitokoh/leopardo-hr', 'lang=en')).toBe('https://github.com/kitokoh/leopardo-hr')
    expect(withLocaleHref('mailto:hello@leopardo-rh.com', 'lang=en')).toBe('mailto:hello@leopardo-rh.com')
    expect(withLocaleHref('#main-content', 'lang=en')).toBe('#main-content')
    expect(withLocaleHref('', 'lang=en')).toBe('')
  })
})
