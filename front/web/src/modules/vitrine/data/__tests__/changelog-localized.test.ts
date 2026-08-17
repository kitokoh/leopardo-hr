import { getChangelogReleases, publicChangelogReleases } from '../changelog-public'

describe('getChangelogReleases (#4610 — contenu localisé ×4)', () => {
  it('returns the canonical FR data for fr', () => {
    expect(getChangelogReleases('fr')).toBe(publicChangelogReleases)
  })

  it('returns localized EN content (no FR bullets)', () => {
    const en = getChangelogReleases('en')
    expect(en).toHaveLength(publicChangelogReleases.length)
    const v4_24 = en.find((r) => r.version === '4.24.0')
    expect(v4_24?.title).toBe('First public release — security, CI and quality')
    expect(v4_24?.bullets.join(' ')).not.toContain('Durcissement')
    expect(v4_24?.bullets.join(' ')).toContain('security hardening')
  })

  it('returns localized TR/AR content for every release (fallback FR for none)', () => {
    for (const locale of ['tr', 'ar'] as const) {
      const data = getChangelogReleases(locale)
      expect(data).toHaveLength(publicChangelogReleases.length)
      for (const release of data) {
        expect(release.title.length).toBeGreaterThan(0)
        expect(release.bullets.length).toBeGreaterThan(0)
      }
    }
  })

  it('keeps the FR data identical (no mutation)', () => {
    getChangelogReleases('en')
    expect(publicChangelogReleases[0].title).toBe('Première release publique — sécurité, CI et qualité')
  })
})
