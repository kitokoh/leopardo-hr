import { getPricingPlans } from '../../data/pricing'
import { planNameToCheckoutKey } from '../PricingSection'

/**
 * #3919 — Alignement pricing ↔ checkout (schéma canonique #2977).
 *
 * Le plan affiché sur /pricing (nom + prix mensuel/annuel) doit correspondre
 * à ce que /checkout affiche et facture. Source de vérité backend :
 * api/database/seeders/PlanSeeder.php — Free 0/5emp, Pilot 29/24€/30emp,
 * Operations 99/79€/250emp, Enterprise sur devis.
 */

// Contrat mirroré depuis checkout/page.tsx (PLAN_CONFIG + PLAN_ALIASES).
// Si le checkout change, ce test casse → les deux surfaces restent alignées.
// ADR-0014 (#4456) : Operations = 79 €/mois, 66 €/mois annuel (790 €/an) —
// le 99 €/mois était une erreur du seeder historique (#4421/#4419).
const CHECKOUT_PLANS = {
  free: { label: 'Free', monthly: 0, annual: 0 },
  pilot: { label: 'Pilot', monthly: 29, annual: 24 },
  operations: { label: 'Operations', monthly: 79, annual: 66 },
  enterprise: { label: 'Enterprise', monthly: null, annual: null },
} as const

const LEGACY_TO_CANONICAL: Record<string, keyof typeof CHECKOUT_PLANS | 'free'> = {
  starter: 'pilot',
  business: 'operations',
  scale: 'enterprise',
}

describe('pricing ↔ checkout alignment (#3919)', () => {
  it.each(['fr', 'en', 'tr', 'ar'] as const)('aligns plan names and prices for %s', (locale) => {
    const plans = getPricingPlans(locale)
    // #3883 : le plan Free (0 €/5 emp, PlanSeeder) est de nouveau affiché sur
    // la vitrine — l'ensemble canonique complet est Free/Pilot/Operations/Enterprise.
    expect(plans).toHaveLength(4)

    for (const plan of plans) {
      const key = planNameToCheckoutKey(plan.name)

      const checkout = CHECKOUT_PLANS[key as keyof typeof CHECKOUT_PLANS]
      // no checkout config for this plan → test fails loudly
      expect(checkout).toBeDefined()

      // Nom affiché = nom canonique (Free/Pilot/Operations/Enterprise, #2977)
      expect(checkout.label).toBe(plan.name)

      if (plan.price === '0') {
        // Le plan Free n'a pas de prix facturable : vitrine et checkout
        // s'accordent sur 0 (le parcours réel passe par l'essai guidé /signup).
        expect(checkout.monthly).toBe(0)
        expect(checkout.annual).toBe(0)
        continue
      }
      if (checkout.monthly === null) {
        // Enterprise « sur devis » : pas de prix numérique côté vitrine non plus
        expect(['Sur devis', 'Custom', 'Teklif', 'حسب الطلب'].includes(plan.price)).toBe(true)
        continue
      }
      // Prix mensuel/annuel : la vitrine et le checkout affichent le même montant
      expect(Number(plan.price)).toBe(checkout.monthly)
      expect(Number(plan.annualPrice)).toBe(checkout.annual)
    }
  })

  it('routes legacy names to the same canonical checkout config', () => {
    for (const [legacy, canonical] of Object.entries(LEGACY_TO_CANONICAL)) {
      expect(planNameToCheckoutKey(legacy)).toBe(canonical)
    }
  })

  it('keeps the popular plan pointing to operations on the checkout', () => {
    // getPlanHref : le plan populaire (Operations) cible /checkout?plan=operations
    const plans = getPricingPlans('fr')
    const popular = plans.find((p) => p.popular)
    expect(popular?.name).toBe('Operations')
    expect(planNameToCheckoutKey(popular?.name)).toBe('operations')
  })
})
