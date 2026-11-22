import { computeCustomsDuty } from './logic';

/**
 * Tests droits de douane et TVA à l'import — cas connus + limites.
 */
describe('computeCustomsDuty', () => {
  it('calcule CIF, droit, TVA et coût rendu sur un cas connu', () => {
    // CIF = 10 000 + 1 000 + 200 = 11 200
    // droit 12 % = 1 344 ; base TVA = 12 544 ; TVA 20 % = 2 508,80
    // total taxes = 3 852,80 ; coût rendu = 15 052,80
    const result = computeCustomsDuty({
      goodsValue: 10_000,
      freight: 1_000,
      insurance: 200,
      dutyRatePercent: 12,
      vatRatePercent: 20,
    });

    expect(result?.cifValue).toBeCloseTo(11_200, 10);
    expect(result?.dutyAmount).toBeCloseTo(1_344, 10);
    expect(result?.vatBase).toBeCloseTo(12_544, 10);
    expect(result?.vatAmount).toBeCloseTo(2_508.8, 10);
    expect(result?.totalTaxes).toBeCloseTo(3_852.8, 10);
    expect(result?.landedCost).toBeCloseTo(15_052.8, 10);
  });

  it('applique la TVA sur CIF + droit, pas sur CIF seul', () => {
    // Erreur classique : TVA sur CIF = 2 240 au lieu de 2 508,80.
    const result = computeCustomsDuty({
      goodsValue: 10_000,
      freight: 1_000,
      insurance: 200,
      dutyRatePercent: 12,
      vatRatePercent: 20,
    });

    expect(result?.vatAmount).not.toBeCloseTo(11_200 * 0.2, 2);
  });

  it('accepte des taux à zéro (zone franche, exonération)', () => {
    const result = computeCustomsDuty({
      goodsValue: 5_000,
      freight: 0,
      insurance: 0,
      dutyRatePercent: 0,
      vatRatePercent: 0,
    });

    expect(result?.dutyAmount).toBe(0);
    expect(result?.vatAmount).toBe(0);
    expect(result?.landedCost).toBeCloseTo(5_000, 10);
  });

  it('accepte un taux de 100 % (borne haute)', () => {
    const result = computeCustomsDuty({
      goodsValue: 1_000,
      freight: 0,
      insurance: 0,
      dutyRatePercent: 100,
      vatRatePercent: 0,
    });

    expect(result?.dutyAmount).toBeCloseTo(1_000, 10);
  });

  it('retourne null sur un taux hors [0, 100]', () => {
    const base = {
      goodsValue: 1_000,
      freight: 0,
      insurance: 0,
      dutyRatePercent: 10,
      vatRatePercent: 20,
    };

    expect(computeCustomsDuty({ ...base, dutyRatePercent: -1 })).toBeNull();
    expect(computeCustomsDuty({ ...base, dutyRatePercent: 101 })).toBeNull();
    expect(computeCustomsDuty({ ...base, vatRatePercent: 150 })).toBeNull();
  });

  it('retourne null sur un montant négatif', () => {
    expect(
      computeCustomsDuty({
        goodsValue: -1,
        freight: 0,
        insurance: 0,
        dutyRatePercent: 10,
        vatRatePercent: 20,
      }),
    ).toBeNull();
  });

  it('retourne null sur NaN ou Infinity', () => {
    const base = {
      goodsValue: 1_000,
      freight: 0,
      insurance: 0,
      dutyRatePercent: 10,
      vatRatePercent: 20,
    };

    expect(computeCustomsDuty({ ...base, goodsValue: Number.NaN })).toBeNull();
    expect(computeCustomsDuty({ ...base, freight: Number.POSITIVE_INFINITY })).toBeNull();
    expect(computeCustomsDuty({ ...base, vatRatePercent: Number.NaN })).toBeNull();
  });
});
