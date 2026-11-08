import { computeVatRecap } from './logic';

/**
 * Tests TVA multi-taux — cas connus calculés à la main + cas limites.
 */
describe('computeVatRecap', () => {
  it('calcule une facture à un seul taux (1000 HT à 20 % → 200 TVA, 1200 TTC)', () => {
    const result = computeVatRecap([{ amountHt: 1000, ratePercent: 20 }]);
    expect(result?.totalHt).toBe(1000);
    expect(result?.totalVat).toBeCloseTo(200, 10);
    expect(result?.totalTtc).toBeCloseTo(1200, 10);
    expect(result?.groups).toHaveLength(1);
  });

  it('regroupe les lignes par taux (deux lignes à 20 %)', () => {
    const result = computeVatRecap([
      { amountHt: 100, ratePercent: 20 },
      { amountHt: 200, ratePercent: 20 },
    ]);
    expect(result?.groups).toHaveLength(1);
    expect(result?.groups[0].baseHt).toBe(300);
    expect(result?.groups[0].vat).toBeCloseTo(60, 10);
  });

  it('gère plusieurs taux et les trie par taux croissant', () => {
    const result = computeVatRecap([
      { amountHt: 100, ratePercent: 20 },
      { amountHt: 200, ratePercent: 5.5 },
      { amountHt: 50, ratePercent: 10 },
    ]);
    expect(result?.groups.map((group) => group.ratePercent)).toEqual([5.5, 10, 20]);
    // TVA : 200 × 5,5 % = 11 ; 50 × 10 % = 5 ; 100 × 20 % = 20 → 36
    expect(result?.totalVat).toBeCloseTo(36, 10);
    expect(result?.totalHt).toBeCloseTo(350, 10);
    expect(result?.totalTtc).toBeCloseTo(386, 10);
  });

  it('gère un taux à 0 % (exonéré)', () => {
    const result = computeVatRecap([{ amountHt: 500, ratePercent: 0 }]);
    expect(result?.totalVat).toBe(0);
    expect(result?.totalTtc).toBe(500);
  });

  it('gère un montant HT nul', () => {
    const result = computeVatRecap([{ amountHt: 0, ratePercent: 20 }]);
    expect(result?.totalHt).toBe(0);
    expect(result?.totalVat).toBe(0);
  });

  it('retourne null pour une liste vide', () => {
    expect(computeVatRecap([])).toBeNull();
  });

  it('retourne null pour un montant négatif', () => {
    expect(computeVatRecap([{ amountHt: -100, ratePercent: 20 }])).toBeNull();
  });

  it('retourne null pour un taux négatif', () => {
    expect(computeVatRecap([{ amountHt: 100, ratePercent: -20 }])).toBeNull();
  });

  it('retourne null pour NaN et Infinity', () => {
    expect(computeVatRecap([{ amountHt: Number.NaN, ratePercent: 20 }])).toBeNull();
    expect(
      computeVatRecap([{ amountHt: 100, ratePercent: Number.POSITIVE_INFINITY }]),
    ).toBeNull();
  });

  it('retourne null si une seule ligne parmi plusieurs est invalide', () => {
    expect(
      computeVatRecap([
        { amountHt: 100, ratePercent: 20 },
        { amountHt: -1, ratePercent: 10 },
      ]),
    ).toBeNull();
  });
});
