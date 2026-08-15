import { planNameToCheckoutKey } from '../PricingSection'

describe('planNameToCheckoutKey', () => {
  it.each([
    ['Starter', 'pilot'],
    ['Business', 'operations'],
    ['Enterprise', 'enterprise'],
    ['Pilot', 'pilot'],
    ['Operations', 'operations'],
    ['Scale', 'enterprise'],
  ])('maps %s to %s', (name, expected) => {
    expect(planNameToCheckoutKey(name)).toBe(expected)
  })

  it('keeps unknown and free names on the free fallback', () => {
    expect(planNameToCheckoutKey('Free')).toBe('free')
    expect(planNameToCheckoutKey('')).toBe('free')
    expect(planNameToCheckoutKey(undefined)).toBe('free')
  })
})
