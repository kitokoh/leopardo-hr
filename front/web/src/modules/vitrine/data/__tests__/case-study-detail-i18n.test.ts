import { caseStudyUiCopy } from '../case-studies'
import { readFileSync } from 'fs'
import { join } from 'path'

// Issue #4703 : la page détail /case-studies/[slug] ne doit plus contenir
// de littéraux FR codés en dur (moduleLabel, CTA) — tout passe par
// caseStudyUiCopy, localisé ×4.

const LOCALES = ['fr', 'en', 'tr', 'ar'] as const
const KEYS = [
  'backLink',
  'resultsTitle',
  'seeAll',
  'ctaTitle',
  'demoCta',
  'moduleIllustrates',
  'moduleExplore',
  'discoverModule',
  'ctaDescription',
  'ctaPrimaryText',
] as const

describe('case study detail i18n (#4703)', () => {
  it('caseStudyUiCopy expose toutes les clés ×4 locales (non vides)', () => {
    for (const locale of LOCALES) {
      const ui = caseStudyUiCopy[locale]
      for (const key of KEYS) {
        expect(ui[key].trim().length).toBeGreaterThan(0)
      }
    }
  })

  it('la page détail ne contient aucun littéral FR hors commentaires', () => {
    const pagePath = join(__dirname, '../../../../app/(landing)/case-studies/[slug]/page.tsx')
    const raw = readFileSync(pagePath, 'utf8')
    const code = raw
      .replace(/\/\*[\s\S]*?\*\//g, '')
      .split('\n')
      .filter((l: string) => !l.trim().startsWith('//'))
      .join('\n')
    const frLines = code.split('\n').filter((l: string) => {
      const matches = l.match(/(['"])[^'"]*[àâäéèêëîïôöùûüçÀ\u00C2\u00C4ÉÈÊËÎÏÔÖÙÛÜÇ][^'"]*\1/g)
      return (matches ?? []).length > 0
    })
    expect(frLines).toEqual([])
  })
})
