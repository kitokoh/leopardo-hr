import { calculatePercent } from './logic';

/**
 * Tests pourcentages — cas connus + cas limites (division par zéro).
 */
describe('calculatePercent', () => {
  it('calcule un pourcentage d’une valeur (20 % de 250 = 50)', () => {
    expect(calculatePercent('percent-of', 250, 20)).toBe(50);
  });

  it('calcule une évolution positive (100 → 150 = +50 %)', () => {
    expect(calculatePercent('evolution', 100, 150)).toBe(50);
  });

  it('calcule une évolution négative (200 → 150 = -25 %)', () => {
    expect(calculatePercent('evolution', 200, 150)).toBe(-25);
  });

  it('calcule une remise (100 avec 30 % de remise = 70)', () => {
    expect(calculatePercent('discount', 100, 30)).toBe(70);
  });

  it('gère une remise de 0 % (valeur inchangée)', () => {
    expect(calculatePercent('discount', 100, 0)).toBe(100);
  });

  it('retourne null pour une évolution depuis zéro (division par zéro)', () => {
    expect(calculatePercent('evolution', 0, 50)).toBeNull();
  });

  it('gère des valeurs négatives', () => {
    expect(calculatePercent('percent-of', -200, 10)).toBe(-20);
    // (-50 − (-100)) ÷ (-100) × 100 = 50 ÷ -100 × 100 = -50 %
    expect(calculatePercent('evolution', -100, -50)).toBe(-50);
  });

  it('retourne null pour NaN et Infinity', () => {
    expect(calculatePercent('percent-of', Number.NaN, 10)).toBeNull();
    expect(calculatePercent('percent-of', 100, Number.POSITIVE_INFINITY)).toBeNull();
  });
});
