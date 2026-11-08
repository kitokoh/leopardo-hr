import { inverseRuleOfThree, ruleOfThree } from './logic';

/**
 * Tests règle de trois — cas connus calculés à la main + cas limites.
 */
describe('ruleOfThree', () => {
  it('résout une proportion simple (3 → 12, donc 5 → 20)', () => {
    expect(ruleOfThree(3, 12, 5)).toBe(20);
  });

  it('résout un prix au kilo (2 kg → 14 €, donc 3 kg → 21 €)', () => {
    expect(ruleOfThree(2, 14, 3)).toBe(21);
  });

  it('gère un rapport inférieur à 1 (10 → 5, donc 4 → 2)', () => {
    expect(ruleOfThree(10, 5, 4)).toBe(2);
  });

  it('gère C = 0 (résultat nul)', () => {
    expect(ruleOfThree(3, 12, 0)).toBe(0);
  });

  it('gère des valeurs négatives', () => {
    // B/A = 12/3 = 4 ; 4 × (-5) = -20
    expect(ruleOfThree(3, 12, -5)).toBe(-20);
  });

  it('gère des décimales (1,5 → 4,5, donc 2,5 → 7,5)', () => {
    expect(ruleOfThree(1.5, 4.5, 2.5)).toBeCloseTo(7.5, 10);
  });

  it('retourne null pour A = 0 (division par zéro)', () => {
    expect(ruleOfThree(0, 12, 5)).toBeNull();
  });

  it('retourne null pour NaN et Infinity', () => {
    expect(ruleOfThree(Number.NaN, 12, 5)).toBeNull();
    expect(ruleOfThree(3, Number.POSITIVE_INFINITY, 5)).toBeNull();
    expect(ruleOfThree(3, 12, Number.NEGATIVE_INFINITY)).toBeNull();
  });
});

describe('inverseRuleOfThree', () => {
  it('résout une proportion inverse (2 ouvriers → 6 jours, donc 3 ouvriers → 4 jours)', () => {
    expect(inverseRuleOfThree(2, 6, 3)).toBe(4);
  });

  it('résout une vitesse (100 km en 2 h, donc 100 km en 4 h → 50 km/h)', () => {
    // A × B = 200 ; 200 ÷ 4 = 50
    expect(inverseRuleOfThree(100, 2, 4)).toBe(50);
  });

  it('retourne null pour C = 0 (division par zéro)', () => {
    expect(inverseRuleOfThree(2, 6, 0)).toBeNull();
  });

  it('retourne null pour NaN et Infinity', () => {
    expect(inverseRuleOfThree(Number.NaN, 6, 3)).toBeNull();
    expect(inverseRuleOfThree(2, 6, Number.POSITIVE_INFINITY)).toBeNull();
  });
});
