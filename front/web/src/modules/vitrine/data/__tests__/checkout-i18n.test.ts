import { readFileSync } from 'node:fs'
import { join } from 'node:path'
import { checkoutCopyByLocale, getCheckoutCopy } from '../checkout'
import type { AppLocale } from '@/lib/i18n'

// Issue #4185 : le tunnel checkout+success doit rester 100 % localisé.
// Ce test garde (a) l'égalité des clés entre les 4 locales et
// (b) l'absence de littéraux FR orphelins dans les pages du tunnel.

const LOCALES: AppLocale[] = ['fr', 'en', 'tr', 'ar']

function deepKeys(obj: unknown, prefix = ''): string[] {
  if (typeof obj !== 'object' || obj === null) return []
  return Object.entries(obj as Record<string, unknown>).flatMap(([k, v]) => [
    prefix ? `${prefix}.${k}` : k,
    ...deepKeys(v, prefix ? `${prefix}.${k}` : k),
  ])
}

describe('checkout i18n (#4185)', () => {
  it('expose les 4 locales de la vitrine', () => {
    for (const locale of LOCALES) {
      expect(checkoutCopyByLocale[locale]).toBeDefined()
    }
  })

  it('a des clés strictement identiques entre toutes les locales', () => {
    const reference = deepKeys(checkoutCopyByLocale.en).sort()
    for (const locale of LOCALES) {
      const keys = deepKeys(checkoutCopyByLocale[locale]).sort()
      expect(keys).toEqual(reference)
    }
  })

  it('aucune chaîne vide dans les locales non-fr (traductions complètes)', () => {
    const nonEmpty = (obj: unknown): string[] => {
      if (typeof obj === 'string') return obj.trim() ? [] : ['<empty string>']
      if (typeof obj !== 'object' || obj === null) return []
      return Object.values(obj as Record<string, unknown>).flatMap((v) => nonEmpty(v))
    }
    for (const locale of ['en', 'tr', 'ar'] as AppLocale[]) {
      expect(nonEmpty(checkoutCopyByLocale[locale])).toEqual([])
    }
  })

  it('retombe sur en pour une locale inconnue', () => {
    expect(getCheckoutCopy('xx' as AppLocale)).toBe(checkoutCopyByLocale.en)
  })

  it('ne laisse aucun littéral FR orphelin dans les pages du tunnel', () => {
    const repoRoot = join(__dirname, '..', '..', '..', '..', '..')
    const pages = [
      join(repoRoot, 'src/app/(landing)/checkout/page.tsx'),
      join(repoRoot, 'src/app/(landing)/checkout/success/page.tsx'),
    ]
    // Mots français typiques qui ne doivent apparaître ni dans les JSX ni
    // dans les littéraux des pages (les commentaires `//` sont autorisés).
    const frTokens = [
      'Continuer avec Google',
      'Créez votre compte',
      'Passer au paiement',
      'Récapitulatif',
      'Paiement sécurisé',
      'Prénom requis',
      'Votre plan sélectionné',
      'Retour aux tarifs',
      'Démarrer l',
      'Numéro de carte',
      'Dû aujourd',
      'Prochaines étapes',
      'Accéder à mon espace',
      'Contacter le support',
      'Mauvais plan',
      'Voir tous les plans',
    ]
    for (const page of pages) {
      const content = readFileSync(page, 'utf-8')
      // Retire les commentaires (lignes commençant par // ou dans /* */)
      const withoutComments = content
        .replace(/\/\*[\s\S]*?\*\//g, '')
        .split('\n')
        .filter((line) => !line.trim().startsWith('//'))
        .join('\n')
      for (const token of frTokens) {
        expect(withoutComments).not.toContain(token)
      }
    }
  })
})
