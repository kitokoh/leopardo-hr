import { computeExportMargin, targetPriceForMarginRate } from './logic';

/**
 * Tests marge et prix cible export — cas connus + limites.
 */
describe('computeExportMargin', () => {
  it('calcule marge, taux de marge, taux de marque et coefficient', () => {
    // coût 100, PV 130 : marge 30 ; markup 30 % ; marque 23,08 % ; coef 1,3
    const result = computeExportMargin({ unitCost: 100, unitPrice: 130 });

    expect(result?.margin).toBeCloseTo(30, 10);
    expect(result?.markupPercent).toBeCloseTo(30, 10);
    expect(result?.marginRatePercent).toBeCloseTo(23.0769, 3);
    expect(result?.coefficient).toBeCloseTo(1.3, 10);
  });

  it('distingue bien taux de marge (sur coût) et taux de marque (sur PV)', () => {
    const result = computeExportMargin({ unitCost: 100, unitPrice: 130 });

    // La confusion des deux est l'erreur n°1 en commerce : elles ne sont
    // JAMAIS égales dès que la marge est non nulle.
    expect(result?.markupPercent).not.toBeCloseTo(result?.marginRatePercent ?? 0, 1);
  });

  it('gère une vente à prix coûtant (marge nulle)', () => {
    const result = computeExportMargin({ unitCost: 100, unitPrice: 100 });

    expect(result?.margin).toBe(0);
    expect(result?.markupPercent).toBe(0);
    expect(result?.marginRatePercent).toBe(0);
    expect(result?.coefficient).toBeCloseTo(1, 10);
  });

  it('gère une vente à perte (marge négative)', () => {
    const result = computeExportMargin({ unitCost: 100, unitPrice: 80 });

    expect(result?.margin).toBeCloseTo(-20, 10);
    expect(result?.markupPercent).toBeCloseTo(-20, 10);
    expect(result?.marginRatePercent).toBeCloseTo(-25, 10);
  });

  it('gère un prix de vente nul : taux de marque indéfini, jamais 0 %', () => {
    const result = computeExportMargin({ unitCost: 100, unitPrice: 0 });

    expect(result?.margin).toBeCloseTo(-100, 10);
    // Une vente à prix nul n'a pas un taux de marque NUL : elle n'en a aucun.
    // Afficher « 0 % » serait un chiffre faux.
    expect(result?.marginRatePercent).toBeNull();
    expect(Number.isNaN(result?.marginRatePercent)).toBe(false);
  });

  it('retourne null sur un coût nul ou négatif', () => {
    expect(computeExportMargin({ unitCost: 0, unitPrice: 100 })).toBeNull();
    expect(computeExportMargin({ unitCost: -1, unitPrice: 100 })).toBeNull();
  });

  it('retourne null sur un prix négatif', () => {
    expect(computeExportMargin({ unitCost: 100, unitPrice: -1 })).toBeNull();
  });

  it('retourne null sur NaN ou Infinity', () => {
    expect(computeExportMargin({ unitCost: Number.NaN, unitPrice: 100 })).toBeNull();
    expect(computeExportMargin({ unitCost: 100, unitPrice: Number.POSITIVE_INFINITY })).toBeNull();
  });
});

describe('targetPriceForMarginRate', () => {
  it('calcule le prix pour un taux de marque visé', () => {
    // 30 % de marque : 100 / 0,7 = 142,857…
    expect(targetPriceForMarginRate(100, 30)).toBeCloseTo(142.857, 3);
  });

  it('vérifie la cohérence : le prix cible donne bien le taux visé', () => {
    const price = targetPriceForMarginRate(250, 40);

    expect(price).not.toBeNull();

    const check = computeExportMargin({ unitCost: 250, unitPrice: price ?? 0 });

    expect(check?.marginRatePercent).toBeCloseTo(40, 6);
  });

  it('retourne le coût quand le taux visé est 0', () => {
    expect(targetPriceForMarginRate(100, 0)).toBeCloseTo(100, 10);
  });

  it('retourne null sur un taux de 100 % ou plus (prix infini)', () => {
    expect(targetPriceForMarginRate(100, 100)).toBeNull();
    expect(targetPriceForMarginRate(100, 150)).toBeNull();
  });

  it('retourne null sur un coût nul, négatif ou non fini', () => {
    expect(targetPriceForMarginRate(0, 30)).toBeNull();
    expect(targetPriceForMarginRate(-1, 30)).toBeNull();
    expect(targetPriceForMarginRate(Number.NaN, 30)).toBeNull();
  });

  it('retourne null sur un taux négatif', () => {
    expect(targetPriceForMarginRate(100, -5)).toBeNull();
  });
});
