import fs from 'node:fs'
import path from 'node:path'
import type { AppLocale } from '@/lib/i18n'
import { SUPPORTED_COUNTRIES_FALLBACK } from '../supported-countries'
import { getTestimonialsPageContent } from '../testimonials-page'
import {
  FREE_TRIAL_DAYS,
  MOBILE_APPS_COUNT,
  PAYROLL_COUNTRIES_COUNT,
  PAYROLL_RULE_ENGINES_COUNT,
  SUPPORTED_LANGUAGES_COUNT,
} from '../vitrine-numbers'

const LOCALES: AppLocale[] = ['fr', 'en', 'tr', 'ar']

/**
 * #7307 — la vitrine annonçait le nombre de pays de paie differemment selon la
 * page : 21 sur l'accueil, 6 sur /testimonials. Un acheteur qui lit deux pages
 * ne sait plus quoi croire, et la credibilite de TOUS les autres chiffres en
 * souffre. Ces tests figent la source unique et interdisent le retour d'un
 * chiffre recopie en dur dans une page.
 */
describe('chiffres canoniques de la vitrine (#7307)', () => {
  it('derive le nombre de pays de paie du registre canonique', () => {
    expect(PAYROLL_COUNTRIES_COUNT).toBe(SUPPORTED_COUNTRIES_FALLBACK.length)
    expect(PAYROLL_COUNTRIES_COUNT).toBeGreaterThan(1)
  })

  it('declare des chiffres plausibles (entiers strictement positifs)', () => {
    const numbers = [
      PAYROLL_COUNTRIES_COUNT,
      PAYROLL_RULE_ENGINES_COUNT,
      SUPPORTED_LANGUAGES_COUNT,
      FREE_TRIAL_DAYS,
      MOBILE_APPS_COUNT,
    ]

    for (const value of numbers) {
      expect(Number.isInteger(value)).toBe(true)
      expect(value).toBeGreaterThan(0)
    }
  })

  it.each(LOCALES)('/testimonials (%s) affiche le nombre de pays CANONIQUE', (locale) => {
    const items = getTestimonialsPageContent(locale).stats.items
    const countries = items.find((item) => /pays|countr|ülke|دول/i.test(item.label))

    expect(countries).toBeDefined()
    expect(countries?.value).toBe(String(PAYROLL_COUNTRIES_COUNT))
  })

  it.each(LOCALES)('/testimonials (%s) affiche la duree d essai canonique', (locale) => {
    const items = getTestimonialsPageContent(locale).stats.items
    const trial = items.find((item) => /essai|trial|deneme|تجريب/i.test(item.label))

    expect(trial).toBeDefined()
    expect(trial?.value).toContain(String(FREE_TRIAL_DAYS))
  })

  it('ne reaffiche plus « 6 pays » (la valeur divergente d origine)', () => {
    for (const locale of LOCALES) {
      const values = getTestimonialsPageContent(locale).stats.items.map((item) => item.value)

      expect(values).not.toContain('6')
    }
  })

  it('garde de source : plus aucun chiffre produit recopie en dur', () => {
    const vitrineRoot = path.resolve(__dirname, '..', '..')
    const read = (relative: string) =>
      fs.readFileSync(path.resolve(vitrineRoot, relative), 'utf8')

    // L'accueil : les 4 stats des 4 locales doivent passer par les constantes.
    const localeSource = read('lib/vitrine-locale.ts')
    for (const literal of [
      "value: 21, suffix: '', label: 'Pays couverts (paie)'",
      "value: 4, suffix: '', label: 'Langues",
      "value: 14, suffix: 'j', label: 'Essai gratuit'",
      "value: 3, suffix: '', label: 'Apps mobiles'",
      "value: 21, suffix: '', label: 'Payroll countries'",
    ]) {
      expect(localeSource).not.toContain(literal)
    }
    expect(localeSource).toContain('PAYROLL_COUNTRIES_COUNT')
    expect(localeSource).toContain('FREE_TRIAL_DAYS')

    // La page demo annoncait « 9 moteurs » alors que l'API en enregistre 11.
    const demoSource = read('../../app/(landing)/demo/page.tsx')
    expect(demoSource).toContain('PAYROLL_RULE_ENGINES_COUNT')
    expect(demoSource).not.toMatch(/desc: '9 (moteurs|rule|kural|محركات)/)
  })
})
