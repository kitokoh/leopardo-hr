import { computeCbm, density, volumetricWeight, VOLUMETRIC_FACTORS } from './logic';

/**
 * Tests CBM / poids volumétrique — cas connus calculés à la main + cas limites.
 */
describe('computeCbm', () => {
  it('calcule le CBM d’un colis de 100 × 50 × 40 cm (0,2 m³)', () => {
    const result = computeCbm({ lengthCm: 100, widthCm: 50, heightCm: 40, mode: 'aerien' });
    expect(result?.unitCbm).toBeCloseTo(0.2, 10);
    expect(result?.totalCbm).toBeCloseTo(0.2, 10);
  });

  it('multiplie par la quantité (10 colis de 0,2 m³ = 2 m³)', () => {
    const result = computeCbm({
      lengthCm: 100,
      widthCm: 50,
      heightCm: 40,
      quantity: 10,
      mode: 'maritime',
    });
    expect(result?.totalCbm).toBeCloseTo(2, 10);
  });

  it('applique le facteur aérien 167 kg/m³ (0,2 m³ → 33,4 kg)', () => {
    const result = computeCbm({ lengthCm: 100, widthCm: 50, heightCm: 40, mode: 'aerien' });
    expect(result?.volumetricWeightKg).toBeCloseTo(33.4, 10);
  });

  it('applique le facteur routier 333 kg/m³ (0,2 m³ → 66,6 kg)', () => {
    const result = computeCbm({ lengthCm: 100, widthCm: 50, heightCm: 40, mode: 'routier' });
    expect(result?.volumetricWeightKg).toBeCloseTo(66.6, 10);
  });

  it('applique le facteur maritime 1000 kg/m³ (1 m³ → 1000 kg)', () => {
    const result = computeCbm({ lengthCm: 100, widthCm: 100, heightCm: 100, mode: 'maritime' });
    expect(result?.volumetricWeightKg).toBeCloseTo(1000, 10);
  });

  it('retient le poids réel quand il dépasse le poids volumétrique', () => {
    const result = computeCbm({
      lengthCm: 100,
      widthCm: 50,
      heightCm: 40,
      actualWeightKg: 50,
      mode: 'aerien',
    });
    // volumétrique = 33,4 kg < 50 kg réel
    expect(result?.chargeableWeightKg).toBe(50);
  });

  it('retient le poids volumétrique quand il dépasse le poids réel', () => {
    const result = computeCbm({
      lengthCm: 100,
      widthCm: 50,
      heightCm: 40,
      actualWeightKg: 10,
      mode: 'aerien',
    });
    expect(result?.chargeableWeightKg).toBeCloseTo(33.4, 10);
  });

  it('utilise la quantité 1 et le poids 0 par défaut', () => {
    const result = computeCbm({ lengthCm: 100, widthCm: 100, heightCm: 100, mode: 'maritime' });
    expect(result?.totalCbm).toBeCloseTo(1, 10);
    expect(result?.chargeableWeightKg).toBeCloseTo(1000, 10);
  });

  it('retourne null pour une dimension nulle ou négative', () => {
    expect(computeCbm({ lengthCm: 0, widthCm: 50, heightCm: 40, mode: 'aerien' })).toBeNull();
    expect(computeCbm({ lengthCm: 100, widthCm: -50, heightCm: 40, mode: 'aerien' })).toBeNull();
  });

  it('retourne null pour une quantité < 1', () => {
    expect(
      computeCbm({ lengthCm: 100, widthCm: 50, heightCm: 40, quantity: 0, mode: 'aerien' }),
    ).toBeNull();
  });

  it('retourne null pour un poids réel négatif', () => {
    expect(
      computeCbm({ lengthCm: 100, widthCm: 50, heightCm: 40, actualWeightKg: -1, mode: 'aerien' }),
    ).toBeNull();
  });

  it('retourne null pour NaN et Infinity', () => {
    expect(
      computeCbm({ lengthCm: Number.NaN, widthCm: 50, heightCm: 40, mode: 'aerien' }),
    ).toBeNull();
    expect(
      computeCbm({
        lengthCm: 100,
        widthCm: 50,
        heightCm: Number.POSITIVE_INFINITY,
        mode: 'aerien',
      }),
    ).toBeNull();
  });
});

describe('volumetricWeight', () => {
  it('calcule le poids volumétrique d’un volume donné', () => {
    expect(volumetricWeight(1, 'aerien')).toBe(167);
    expect(volumetricWeight(1, 'routier')).toBe(333);
    expect(volumetricWeight(1, 'maritime')).toBe(1000);
  });

  it('retourne null pour un volume nul ou négatif', () => {
    expect(volumetricWeight(0, 'aerien')).toBeNull();
    expect(volumetricWeight(-1, 'aerien')).toBeNull();
  });

  it('retourne null pour NaN', () => {
    expect(volumetricWeight(Number.NaN, 'aerien')).toBeNull();
  });
});

describe('density', () => {
  it('calcule une densité (100 kg pour 0,5 m³ = 200 kg/m³)', () => {
    expect(density(100, 0.5)).toBe(200);
  });

  it('retourne null pour un volume nul (division par zéro)', () => {
    expect(density(100, 0)).toBeNull();
  });

  it('retourne null pour un poids négatif ou NaN', () => {
    expect(density(-1, 1)).toBeNull();
    expect(density(Number.NaN, 1)).toBeNull();
  });
});

describe('VOLUMETRIC_FACTORS', () => {
  it('expose les trois facteurs documentés', () => {
    expect(VOLUMETRIC_FACTORS).toEqual({ aerien: 167, routier: 333, maritime: 1000 });
  });
});
