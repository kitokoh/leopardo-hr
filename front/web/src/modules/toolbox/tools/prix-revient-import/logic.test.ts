import { computeImportUnitCost } from './logic';

/**
 * Tests prix de revient à l'import — cas connus calculés à la main + limites.
 */
describe('computeImportUnitCost', () => {
  it('additionne les six postes et divise par la quantité', () => {
    // 10 000 + 1 500 + 200 + 800 + 2 000 + 300 = 14 800 ; / 100 = 148
    const result = computeImportUnitCost({
      purchase: 10_000,
      freight: 1_500,
      insurance: 200,
      duty: 800,
      taxes: 2_000,
      otherFees: 300,
      quantity: 100,
    });

    expect(result?.totalCost).toBeCloseTo(14_800, 10);
    expect(result?.unitCost).toBeCloseTo(148, 10);
  });

  it('calcule la part de chaque poste dans le total', () => {
    // Total 1 000 : achat 500 (50 %), fret 250 (25 %), reste 250 (25 %)
    const result = computeImportUnitCost({
      purchase: 500,
      freight: 250,
      insurance: 0,
      duty: 0,
      taxes: 250,
      otherFees: 0,
      quantity: 1,
    });

    expect(result?.breakdown.purchase).toBeCloseTo(50, 10);
    expect(result?.breakdown.freight).toBeCloseTo(25, 10);
    expect(result?.breakdown.taxes).toBeCloseTo(25, 10);
    expect(result?.breakdown.insurance).toBeCloseTo(0, 10);
  });

  it('accepte des postes à zéro (saisie partielle explicite)', () => {
    const result = computeImportUnitCost({
      purchase: 1_000,
      freight: 0,
      insurance: 0,
      duty: 0,
      taxes: 0,
      otherFees: 0,
      quantity: 10,
    });

    expect(result?.totalCost).toBeCloseTo(1_000, 10);
    expect(result?.unitCost).toBeCloseTo(100, 10);
  });

  it('retourne null sur quantité nulle (division par zéro)', () => {
    expect(
      computeImportUnitCost({
        purchase: 1_000,
        freight: 0,
        insurance: 0,
        duty: 0,
        taxes: 0,
        otherFees: 0,
        quantity: 0,
      }),
    ).toBeNull();
  });

  it('retourne null sur quantité négative', () => {
    expect(
      computeImportUnitCost({
        purchase: 1_000,
        freight: 0,
        insurance: 0,
        duty: 0,
        taxes: 0,
        otherFees: 0,
        quantity: -5,
      }),
    ).toBeNull();
  });

  it('retourne null sur un poste négatif', () => {
    expect(
      computeImportUnitCost({
        purchase: -1,
        freight: 0,
        insurance: 0,
        duty: 0,
        taxes: 0,
        otherFees: 0,
        quantity: 1,
      }),
    ).toBeNull();
  });

  it('retourne null sur NaN ou Infinity', () => {
    const base = {
      purchase: 1_000,
      freight: 0,
      insurance: 0,
      duty: 0,
      taxes: 0,
      otherFees: 0,
      quantity: 1,
    };

    expect(computeImportUnitCost({ ...base, purchase: Number.NaN })).toBeNull();
    expect(computeImportUnitCost({ ...base, freight: Number.POSITIVE_INFINITY })).toBeNull();
    expect(computeImportUnitCost({ ...base, quantity: Number.NaN })).toBeNull();
  });

  it('ne produit jamais NaN quand le total est nul', () => {
    const result = computeImportUnitCost({
      purchase: 0,
      freight: 0,
      insurance: 0,
      duty: 0,
      taxes: 0,
      otherFees: 0,
      quantity: 1,
    });

    expect(result?.totalCost).toBe(0);
    expect(result?.unitCost).toBe(0);
    expect(Number.isNaN(result?.breakdown.purchase)).toBe(false);
  });
});
