import { getModulePageContent, modulePageContent, modulePageContentByLocale } from '../content'

// Issue #4196 : les pages modules de la vitrine doivent rester localisées.
// Lot 1 : « employes » traduit en/tr/ar ; les autres modules retombent sur FR
// (fusion getModulePageContent). Ce test garantit que :
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
      // Lot 1 : seul employes doit être fourni (les lots suivants l'étendent).
      expect(providedModules).toEqual(['employes'])
      for (const mod of providedModules) {
        const reference = deepKeys(modulePageContent[mod as keyof typeof modulePageContent]).sort()
        const actual = deepKeys(partial[mod as keyof typeof partial]).sort()
        expect(actual).toEqual(reference)
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
