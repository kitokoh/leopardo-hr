import { getPricingPlans } from '@/modules/vitrine/data/pricing'
import { planNameToCheckoutKey } from '../PricingSection'

// Issue #3919 — un SEUL schéma de plans (noms + prix) doit exister entre la
// vitrine /pricing et le /checkout. Source de vérité : PlanSeeder.php
// (Starter 29 / Business 79 / Enterprise 199, 14 jours d'essai).
const EXPECTED_PLANS = [
  { name: 'Starter', price: '29' },
  { name: 'Business', price: '79' },
  { name: 'Enterprise', price: '199' },
] as const

describe('pricing ↔ checkout alignment (issue #3919)', () => {
  it('every locale advertises the same canonical plan names and prices', () => {
    for (const locale of ['fr', 'en', 'tr', 'ar'] as const) {
      const plans = getPricingPlans(locale)
      for (const expected of EXPECTED_PLANS) {
        const plan = plans.find((p) => p.name === expected.name)
        expect(plan).toBeDefined()
        expect(plan!.price).toBe(expected.price)
      }
    }
  })

  it('every pricing plan name resolves to a checkout plan key', () => {
    const plans = getPricingPlans('fr')
    for (const plan of plans) {
      expect(planNameToCheckoutKey(plan.name)).not.toBe('free')
    }
  })

  it('the canonical monthly prices match PlanSeeder documentation', () => {
    const plans = getPricingPlans('fr')
    const byName = Object.fromEntries(plans.map((p) => [p.name, p.price]))
    expect(byName).toMatchObject({
      Starter: '29',
      Business: '79',
      Enterprise: '199',
    })
  })
})
