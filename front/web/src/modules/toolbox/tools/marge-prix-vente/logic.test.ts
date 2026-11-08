import { computeMargin, priceFromMarginRate, priceFromMarkupRate } from './logic';

/**
 * Tests marge / prix de vente — cas connus calculés à la main + cas limites.
 */
describe('computeMargin', () => {
  it('calcule une marge de 30 sur un achat de 100 vendu 130', () => {
    const result = computeMargin({ cost: 100, price: 130 });
    expect(result?.margin).toBe(30);
    expect(result?.marginRate).toBeCloseTo(30, 10);
    expect(result?.markupRate).toBeCloseTo(23.0769230769, 8);
    expect(result?.coefficient).toBeCloseTo(1.3, 10);
  });

  it('calcule une marge nulle (PA = PV)', () => {
    const result = computeMargin({ cost: 50, price: 50 });
    expect(result?.margin).toBe(0);
    expect(result?.marginRate).toBe(0);
    expect(result?.markupRate).toBe(0);
    expect(result?.coefficient).toBe(1);
  });

  it('calcule une marge négative (vente à perte)', () => {
    const result = computeMargin({ cost: 100, price: 80 });
    expect(result?.margin).toBe(-20);
    expect(result?.marginRate).toBeCloseTo(-20, 10);
    expect(result?.markupRate).toBeCloseTo(-25, 10);
  });

  it('retourne null pour le taux de marge si PA = 0 (division par zéro)', () => {
    const result = computeMargin({ cost: 0, price: 100 });
    expect(result?.margin).toBe(100);
    expect(result?.marginRate).toBeNull();
    expect(result?.coefficient).toBeNull();
    expect(result?.markupRate).toBeCloseTo(100, 10);
  });

  it('retourne null pour le taux de marque si PV = 0', () => {
    const result = computeMargin({ cost: 0, price: 0 });
    expect(result?.markupRate).toBeNull();
    expect(result?.marginRate).toBeNull();
  });

  it('retourne null pour un montant négatif', () => {
    expect(computeMargin({ cost: -1, price: 100 })).toBeNull();
    expect(computeMargin({ cost: 100, price: -1 })).toBeNull();
  });

  it('retourne null pour NaN et Infinity', () => {
    expect(computeMargin({ cost: Number.NaN, price: 100 })).toBeNull();
    expect(computeMargin({ cost: 100, price: Number.POSITIVE_INFINITY })).toBeNull();
  });
});

describe('priceFromMarginRate', () => {
  it('calcule un PV depuis un taux de marge (100 + 30 % = 130)', () => {
    expect(priceFromMarginRate(100, 30)).toBeCloseTo(130, 10);
  });

  it('gère un taux de marge nul (PV = PA)', () => {
    expect(priceFromMarginRate(100, 0)).toBe(100);
  });

  it('gère un taux de marge négatif (PV < PA)', () => {
    expect(priceFromMarginRate(100, -20)).toBeCloseTo(80, 10);
  });

  it('retourne null pour un coût négatif ou NaN', () => {
    expect(priceFromMarginRate(-1, 30)).toBeNull();
    expect(priceFromMarginRate(Number.NaN, 30)).toBeNull();
  });
});

describe('priceFromMarkupRate', () => {
  it('calcule un PV depuis un taux de marque (100 ÷ (1 − 20 %) = 125)', () => {
    expect(priceFromMarkupRate(100, 20)).toBeCloseTo(125, 10);
  });

  it('gère un taux de marque nul (PV = PA)', () => {
    expect(priceFromMarkupRate(100, 0)).toBe(100);
  });

  it('retourne null pour un taux de marque ≥ 100 % (prix indéfini)', () => {
    expect(priceFromMarkupRate(100, 100)).toBeNull();
    expect(priceFromMarkupRate(100, 150)).toBeNull();
  });

  it('retourne null pour un coût négatif ou NaN', () => {
    expect(priceFromMarkupRate(-1, 20)).toBeNull();
    expect(priceFromMarkupRate(Number.NaN, 20)).toBeNull();
  });
});
