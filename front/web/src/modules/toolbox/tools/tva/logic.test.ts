import { calculateVat } from './logic';

/**
 * Tests TVA — cas connus + cas limites.
 *
 * Cas de référence : 100 € HT à 20 % → TTC 120 €, TVA 20 €.
 */
describe('calculateVat', () => {
  it('ajoute la TVA (100 HT à 20 % → 120 TTC)', () => {
    expect(calculateVat({ amount: 100, ratePercent: 20, direction: 'ht-to-ttc' })).toEqual({
      ht: 100,
      vat: 20,
      ttc: 120,
    });
  });

  it('retire la TVA (120 TTC à 20 % → 100 HT)', () => {
    expect(calculateVat({ amount: 120, ratePercent: 20, direction: 'ttc-to-ht' })).toEqual({
      ht: 100,
      vat: 20,
      ttc: 120,
    });
  });

  it('gère le taux réduit 5,5 % (100 HT → 105,5 TTC)', () => {
    expect(calculateVat({ amount: 100, ratePercent: 5.5, direction: 'ht-to-ttc' })).toEqual({
      ht: 100,
      vat: 5.5,
      ttc: 105.5,
    });
  });

  it('gère un taux nul (TTC = HT)', () => {
    expect(calculateVat({ amount: 50, ratePercent: 0, direction: 'ht-to-ttc' })).toEqual({
      ht: 50,
      vat: 0,
      ttc: 50,
    });
  });

  it('gère un montant nul', () => {
    expect(calculateVat({ amount: 0, ratePercent: 20, direction: 'ht-to-ttc' })).toEqual({
      ht: 0,
      vat: 0,
      ttc: 0,
    });
  });

  it('retourne null pour un montant ou un taux négatif', () => {
    expect(calculateVat({ amount: -100, ratePercent: 20, direction: 'ht-to-ttc' })).toBeNull();
    expect(calculateVat({ amount: 100, ratePercent: -20, direction: 'ht-to-ttc' })).toBeNull();
  });

  it('retourne null pour NaN et Infinity', () => {
    expect(calculateVat({ amount: Number.NaN, ratePercent: 20, direction: 'ht-to-ttc' })).toBeNull();
    expect(
      calculateVat({ amount: 100, ratePercent: Number.POSITIVE_INFINITY, direction: 'ht-to-ttc' }),
    ).toBeNull();
  });

  it('ne produit jamais Infinity sur un taux extrême', () => {
    const result = calculateVat({ amount: 100, ratePercent: 1e6, direction: 'ht-to-ttc' });
    expect(result).not.toBeNull();
    expect(Number.isFinite(result!.ttc)).toBe(true);
  });
});
