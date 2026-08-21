import type { AppLocale } from '@/lib/i18n'
import { t } from '@/lib/i18n/locale-catalog'

/**
 * Source de vérité des montants — alignée sur api/database/seeders/PlanSeeder.php
 * et PlanCode.php (ADR-0014 · 2026-08-15).
 *
 * Plans canoniques :
 *   free        0 €/mois · 5 employés max · 14 jours d'essai (unifié #4951)
 *   pilot      29 €/mois (24,17 €/mois annuel = 290 €/an) · 30 employés max · 14j
 *   operations 79 €/mois (65,83 €/mois annuel = 790 €/an) · 200 employés max · 14j
 *   enterprise  sur devis · illimité · 14j
 *
 * ⚠ Les anciens libellés "Starter" et "Business" sont des alias legacy migrés
 * par PlanSeeder.migrateLegacyPlanNames(). Ne plus les utiliser ici.
 * Le champ `planCode` est envoyé tel quel au checkout — doit correspondre
 * exactement aux valeurs de PlanCode.php.
 *
 * Les libellés visibles (description, features, CTA…) vivent dans le catalogue
 * i18n partagé (namespace `pricing.*`, #2755 lot 2) — ce module ne garde que
 * les valeurs machine (prix, plafonds, codes) et reconstruit les libellés
 * par locale via t().
 */

export type PricingPlan = {
  /** Code métier envoyé au checkout et à l'API — doit être un PlanCode valide */
  planCode: 'free' | 'pilot' | 'operations' | 'enterprise'
  name: string
  price: string
  annualPrice: string
  period: string
  annualPeriod: string
  description: string
  priceNote?: string
  features: string[]
  cta: string
  popular: boolean
  gradient: string
  employeeLimit: string
}

function buildFreePlan(locale: AppLocale): PricingPlan {
  return {
    planCode: 'free',
    name: 'Free',
    price: '0',
    annualPrice: '0',
    period: t(locale, 'pricing.plans.free.period'),
    annualPeriod: t(locale, 'pricing.plans.free.annualPeriod'),
    description: t(locale, 'pricing.plans.free.description'),
    priceNote: t(locale, 'pricing.plans.free.priceNote'),
    employeeLimit: t(locale, 'pricing.plans.free.employeeLimit'),
    features: [
      t(locale, 'pricing.plans.free.features.0'),
      t(locale, 'pricing.plans.free.features.1'),
      t(locale, 'pricing.plans.free.features.2'),
      t(locale, 'pricing.plans.free.features.3'),
      t(locale, 'pricing.plans.free.features.4'),
      t(locale, 'pricing.plans.free.features.5'),
    ],
    cta: t(locale, 'pricing.plans.free.cta'),
    popular: false,
    gradient: 'from-gray-500 to-gray-600',
  }
}

function buildPilotPlan(locale: AppLocale): PricingPlan {
  return {
    planCode: 'pilot',
    name: 'Pilot',
    price: '29',
    annualPrice: '24,17',
    period: t(locale, 'pricing.plans.pilot.period'),
    annualPeriod: t(locale, 'pricing.plans.pilot.annualPeriod'),
    description: t(locale, 'pricing.plans.pilot.description'),
    priceNote: t(locale, 'pricing.plans.pilot.priceNote'),
    employeeLimit: t(locale, 'pricing.plans.pilot.employeeLimit'),
    features: [
      t(locale, 'pricing.plans.pilot.features.0'),
      t(locale, 'pricing.plans.pilot.features.1'),
      t(locale, 'pricing.plans.pilot.features.2'),
      t(locale, 'pricing.plans.pilot.features.3'),
      t(locale, 'pricing.plans.pilot.features.4'),
      t(locale, 'pricing.plans.pilot.features.5'),
      t(locale, 'pricing.plans.pilot.features.6'),
    ],
    cta: t(locale, 'pricing.plans.pilot.cta'),
    popular: false,
    gradient: 'from-slate-600 to-slate-700',
  }
}

function buildOperationsPlan(locale: AppLocale): PricingPlan {
  return {
    planCode: 'operations',
    name: 'Operations',
    price: '79',
    annualPrice: '65,83',
    period: t(locale, 'pricing.plans.operations.period'),
    annualPeriod: t(locale, 'pricing.plans.operations.annualPeriod'),
    description: t(locale, 'pricing.plans.operations.description'),
    priceNote: t(locale, 'pricing.plans.operations.priceNote'),
    employeeLimit: t(locale, 'pricing.plans.operations.employeeLimit'),
    features: [
      t(locale, 'pricing.plans.operations.features.0'),
      t(locale, 'pricing.plans.operations.features.1'),
      t(locale, 'pricing.plans.operations.features.2'),
      t(locale, 'pricing.plans.operations.features.3'),
      t(locale, 'pricing.plans.operations.features.4'),
      t(locale, 'pricing.plans.operations.features.5'),
      t(locale, 'pricing.plans.operations.features.6'),
    ],
    cta: t(locale, 'pricing.plans.operations.cta'),
    popular: true,
    gradient: 'from-emerald-500 to-cyan-500',
  }
}

function buildEnterprisePlan(locale: AppLocale): PricingPlan {
  return {
    planCode: 'enterprise',
    name: 'Enterprise',
    price: 'Sur devis',
    annualPrice: 'Sur devis',
    period: '',
    annualPeriod: '',
    description: t(locale, 'pricing.plans.enterprise.description'),
    priceNote: t(locale, 'pricing.plans.enterprise.priceNote'),
    employeeLimit: t(locale, 'pricing.plans.enterprise.employeeLimit'),
    features: [
      t(locale, 'pricing.plans.enterprise.features.0'),
      t(locale, 'pricing.plans.enterprise.features.1'),
      t(locale, 'pricing.plans.enterprise.features.2'),
      t(locale, 'pricing.plans.enterprise.features.3'),
      t(locale, 'pricing.plans.enterprise.features.4'),
      t(locale, 'pricing.plans.enterprise.features.5'),
    ],
    cta: t(locale, 'pricing.plans.enterprise.cta'),
    popular: false,
    gradient: 'from-violet-600 to-purple-700',
  }
}

const pricingByLocale: Record<AppLocale, PricingPlan[]> = {
  fr: [buildFreePlan('fr'), buildPilotPlan('fr'), buildOperationsPlan('fr'), buildEnterprisePlan('fr')],
  en: [buildFreePlan('en'), buildPilotPlan('en'), buildOperationsPlan('en'), buildEnterprisePlan('en')],
  tr: [buildFreePlan('tr'), buildPilotPlan('tr'), buildOperationsPlan('tr'), buildEnterprisePlan('tr')],
  ar: [buildFreePlan('ar'), buildPilotPlan('ar'), buildOperationsPlan('ar'), buildEnterprisePlan('ar')],
}

export const pricing = pricingByLocale

export function getPricingPlans(locale: AppLocale): PricingPlan[] {
  return pricingByLocale[locale] ?? pricingByLocale.fr
}

/**
 * #4404 — source unique « prix machine vs devis ».
 * Un plan « sur devis » n'a pas de prix machine : la carte ne doit afficher
 * aucun montant et son CTA doit mener au contact, jamais au checkout.
 * Libellés « devis » par locale — alignés sur la clé catalogue
 * `pricing.plans.customPrice` (attention aux mots AR distincts : « حسب الطلب »
 * (sur demande) est le libellé de données ; « حسب العرض » (selon l'offre) n'est
 * PAS utilisé).
 */
export function showsCurrency(price: string): boolean {
  return !['Sur devis', 'Custom', 'Teklif', 'Teklif alın', 'حسب الطلب'].includes(price)
}
