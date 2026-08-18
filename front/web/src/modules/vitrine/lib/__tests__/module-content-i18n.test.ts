import { getModulePageContent, modulePageContent, modulePageContentByLocale } from '../content'

// Issue #4196 : les pages modules de la vitrine doivent rester localisées.
// Lot 1 : « employes » traduit en/tr/ar ; lot 2 : documents/comptabilite/
// marketing (les 4 modules complets). Ce test garantit que :
//   1. le FR reste la référence complète (4 modules) ;
//   2. chaque locale partielle n'expose que des modules complets (clés égales
//      à la référence FR pour le module fourni) ;
//   3. getModulePageContent(locale) rend bien un objet complet.

const FR_MODULES = Object.keys(modulePageContent).sort()

function deepKeys(obj: unknown, prefix = ''): string[] {
  if (typeof obj !== 'object' || obj === null) return []
  return Object.entries(obj as Record<string, unknown>).flatMap(([k, v]) => [
    prefix ? `${prefix}.${k}` : k,
    ...deepKeys(v, prefix ? `${prefix}.${k}` : k),
  ])
}

describe('module page content i18n (#4196)', () => {
  it('expose les 4 modules en FR (référence complète)', () => {
    expect(FR_MODULES).toEqual(['comptabilite', 'documents', 'employes', 'marketing'])
  })

  it('chaque module fourni par une locale non-fr a exactement les clés FR', () => {
    for (const locale of ['en', 'tr', 'ar'] as const) {
      const partial = modulePageContentByLocale[locale]
      const providedModules = Object.keys(partial).sort()
      // Lots 1+2 : les 4 modules sont fournis pour chaque locale.
      expect(providedModules).toEqual(['comptabilite', 'documents', 'employes', 'marketing'])
      for (const mod of providedModules) {
        const reference = deepKeys(modulePageContent[mod as keyof typeof modulePageContent]).sort()
        const actual = deepKeys(partial[mod as keyof typeof partial]).sort()
        expect(actual).toEqual(reference)
      }
    }
  })

  it('aucune valeur vide ou placeholder dans les modules traduits', () => {
    for (const locale of ['en', 'tr', 'ar'] as const) {
      const partial = modulePageContentByLocale[locale]
      for (const mod of ['comptabilite', 'documents', 'employes', 'marketing'] as const) {
        const textValues = deepKeys(partial[mod] ?? {})
          .map((k) => {
            const cursor = (partial[mod] as Record<string, unknown>)
            const segs = k.split('.')
            let v: unknown = cursor
            for (const s of segs) v = (v as Record<string, unknown>)?.[s]
            return typeof v === 'string' ? v : ''
          })
          .filter(Boolean)
        expect(textValues.length).toBeGreaterThan(0)
        for (const t of textValues) {
          // Le but du garde est de détecter les valeurs VIDES ou placeholders
          // techniques, pas les chaînes courtes légitimes : valeurs métriques
          // (`'0'` erreurs paie, `'50'` emplacements, content.ts caseStudies)
          // ou abréviations (« İK » = RH en turc). Régression garde #4196
          // constatée à l'audit 2026-08-16.
          expect(t.trim().length).toBeGreaterThan(0)
          // Pas de placeholder technique résiduel type « TODO » ou clé brute
          expect(t).not.toMatch(/^(en|tr|ar)\./)
        }
      }
    }
  })

  it('getModulePageContent fusionne la locale sur le FR pour tous les modules', () => {
    for (const locale of ['fr', 'en', 'tr', 'ar'] as const) {
      const merged = getModulePageContent(locale)
      expect(Object.keys(merged).sort()).toEqual(FR_MODULES)
      for (const mod of FR_MODULES) {
        const k = mod as keyof typeof merged
        expect(merged[k]).toBeDefined()
      }
    }
  })
})

describe('module page sections i18n (#4702)', () => {
  it('les badges/titres de section sont localisés pour les 4 modules × 4 locales', () => {
    for (const locale of ['fr', 'en', 'tr', 'ar'] as const) {
      const merged = getModulePageContent(locale)
      for (const mod of FR_MODULES) {
        const sections = merged[mod as keyof typeof merged].sections
        expect(sections).toBeDefined()
        for (const key of ['heroBadge', 'problemBadge', 'solutionBadge', 'featuresTitle', 'featuresSubtitle', 'featuresBadge'] as const) {
          expect(sections[key].trim().length).toBeGreaterThan(0)
          // Pas de placeholder technique ni de clé brute
          expect(sections[key]).not.toMatch(/^(en|tr|ar)\./)
        }
      }
    }
  })

  it('ne contient aucun littéral FR hardcodé dans les pages modules', () => {
    const fs = require('fs')
    const path = require('path')
    const base = path.join(__dirname, '../../../../app/(landing)')
    for (const mod of ['employes', 'documents', 'comptabilite', 'marketing']) {
      const raw = fs.readFileSync(path.join(base, mod, 'page.tsx'), 'utf8')
      // Retire les commentaires // et /* */ puis analyse ligne par ligne :
      // seuls les littéraux du CODE réel comptent (commentaires FR OK).
      const code = raw
        .replace(/\/\*[\s\S]*?\*\//g, '')
        .split('\n')
        .filter((l: string) => !l.trim().startsWith('//'))
        .join('\n')
      const frLines = code.split('\n').filter((l: string) => {
        const matches = l.match(/(['"])[^'"]*[àâäéèêëîïôöùûüçÀÂÄÉÈÊËÎÏÔÖÙÛÜÇ][^'"]*\1/g)
        return (matches ?? []).length > 0
      })
      expect(frLines).toEqual([])
    }
  })
})
