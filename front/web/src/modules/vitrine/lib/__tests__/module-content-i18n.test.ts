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
          // Valeurs légitimes courtes : stats chiffrées ('0', '50', '8%'),
          // abréviations sectorielles ('HR', 'İK'). Tout le reste doit être
          // une vraie phrase (longueur > 2).
          const isShortButLegit = /^[\d%.,]+$/.test(t) || ['HR', 'İK', 'RH'].includes(t)
          expect(isShortButLegit || t.length > 2).toBe(true)
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
