import { estimateNetSalary } from './logic';

/**
 * Tests salaire brut → net (estimation) — cas connus + cas limites.
 *
 * Cas de référence : 3 000 € brut à 22 % de cotisations → net 2 340 €.
 */
describe('estimateNetSalary', () => {
  it('estime un net connu (3 000 € à 22 % → 2 340 €)', () => {
    expect(estimateNetSalary({ grossMonthly: 3000, contributionRatePercent: 22 })).toEqual({
      net: 2340,
      deductions: 660,
    });
  });

  it('gère un taux nul (net = brut)', () => {
    expect(estimateNetSalary({ grossMonthly: 2000, contributionRatePercent: 0 })).toEqual({
      net: 2000,
      deductions: 0,
    });
  });

  it('gère un taux de 100 % (net = 0)', () => {
    expect(estimateNetSalary({ grossMonthly: 2000, contributionRatePercent: 100 })).toEqual({
      net: 0,
      deductions: 2000,
    });
  });

  it('gère un brut nul', () => {
    expect(estimateNetSalary({ grossMonthly: 0, contributionRatePercent: 22 })).toEqual({
      net: 0,
      deductions: 0,
    });
  });

  it('retourne null pour un brut négatif', () => {
    expect(estimateNetSalary({ grossMonthly: -1000, contributionRatePercent: 22 })).toBeNull();
  });

  it('retourne null pour un taux hors [0, 100]', () => {
    expect(estimateNetSalary({ grossMonthly: 3000, contributionRatePercent: -1 })).toBeNull();
    expect(estimateNetSalary({ grossMonthly: 3000, contributionRatePercent: 101 })).toBeNull();
  });

  it('retourne null pour NaN et Infinity', () => {
    expect(estimateNetSalary({ grossMonthly: Number.NaN, contributionRatePercent: 22 })).toBeNull();
    expect(
      estimateNetSalary({ grossMonthly: 3000, contributionRatePercent: Number.POSITIVE_INFINITY }),
    ).toBeNull();
  });
});
