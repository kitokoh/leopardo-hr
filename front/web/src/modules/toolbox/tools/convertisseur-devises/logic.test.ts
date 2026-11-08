import { convertCurrency } from './logic';

/**
 * Tests convertisseur de devises — cas connus calculés à la main + cas limites.
 *
 * Les taux sont « 1 unité = X EUR » (pivot euro).
 */
describe('convertCurrency', () => {
  it('convertit EUR → EUR sans changement (taux 1 → 1)', () => {
    expect(convertCurrency(100, 1, 1)).toBe(100);
  });

  it('convertit EUR → devise plus faible (100 EUR → DZD, taux DZD = 0,0069)', () => {
    // 100 × 1 = 100 EUR ; 100 / 0,0069 = 14492,75…
    expect(convertCurrency(100, 1, 0.0069)).toBeCloseTo(14492.7536, 2);
  });

  it('convertit devise → EUR (1000 DZD → EUR)', () => {
    // 1000 × 0,0069 = 6,9 EUR ; 6,9 / 1 = 6,9
    expect(convertCurrency(1000, 0.0069, 1)).toBeCloseTo(6.9, 6);
  });

  it('convertit entre deux devises non-euro (USD → DZD)', () => {
    // 100 × 0,92 = 92 EUR ; 92 / 0,0069 = 13333,33…
    expect(convertCurrency(100, 0.92, 0.0069)).toBeCloseTo(13333.3333, 2);
  });

  it('gère un montant nul', () => {
    expect(convertCurrency(0, 0.92, 0.0069)).toBe(0);
  });

  it('retourne null pour un taux nul ou négatif', () => {
    expect(convertCurrency(100, 0, 1)).toBeNull();
    expect(convertCurrency(100, 1, -0.5)).toBeNull();
  });

  it('retourne null pour un montant ou un taux non fini', () => {
    expect(convertCurrency(Number.NaN, 1, 1)).toBeNull();
    expect(convertCurrency(100, Number.POSITIVE_INFINITY, 1)).toBeNull();
  });
});
