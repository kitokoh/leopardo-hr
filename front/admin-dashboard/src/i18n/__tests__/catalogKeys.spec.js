import { describe, it, expect } from 'vitest'
import { messages, supportedLocales, translate } from '@/i18n/index.js'

/**
 * #7434 — toute chaîne utilisateur doit exister dans les 4 locales.
 *
 * Le dépôt a une garde de parité (`check-i18n-catalog-parity.sh`) mais elle ne
 * couvre PAS les catalogues de `front/admin-dashboard` : ce test verrouille
 * donc les clés introduites par le lot cohérence i18n.
 */
const REQUIRED_KEYS = [
  'common.actions',
  'common.search',
  'common.edit',
  'common.delete',
  'users.table.name',
  'users.table.status',
  'users.table.company',
  'users.table.createdAt',
  'users.status.active',
  'users.status.inactive',
  'users.status.suspended',
  'users.status.pending',
]

describe('catalogues admin — clés du lot #7434', () => {
  it('expose les 4 locales', () => {
    expect(supportedLocales.sort()).toEqual(['ar', 'en', 'fr', 'tr'])
    expect(Object.keys(messages).sort()).toEqual(['ar', 'en', 'fr', 'tr'])
  })

  it.each(supportedLocales)('la locale %s porte toutes les clés requises', (locale) => {
    for (const key of REQUIRED_KEYS) {
      const value = translate(locale, key)
      expect(value, `${locale} → ${key} manquant`).not.toBe('')
      expect(value, `${locale} → ${key} non traduit`).not.toBe(key)
    }
  })
})
