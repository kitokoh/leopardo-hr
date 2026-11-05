import { calculateImc, classifyImc } from './logic';

/**
 * Tests IMC — cas connus calculés à la main + cas limites.
 *
 * Cas de référence : 70 kg / 175 cm → 70 ÷ 1,75² = 70 ÷ 3,0625 = 22,857… → 22,9
 */
describe('calculateImc', () => {
  it('calcule un IMC connu (70 kg / 175 cm → 22,9)', () => {
    expect(calculateImc({ weightKg: 70, heightCm: 175 })).toEqual({ imc: 22.9, category: 'normal' });
  });

  it('calcule un IMC connu (100 kg / 200 cm → 25,0 → surpoids)', () => {
    expect(calculateImc({ weightKg: 100, heightCm: 200 })).toEqual({ imc: 25, category: 'overweight' });
  });

  it('classe une insuffisance pondérale (50 kg / 180 cm → 15,4)', () => {
    expect(calculateImc({ weightKg: 50, heightCm: 180 })).toEqual({ imc: 15.4, category: 'underweight' });
  });

  it('classe une obésité (120 kg / 170 cm → 41,5)', () => {
    expect(calculateImc({ weightKg: 120, heightCm: 170 })).toEqual({ imc: 41.5, category: 'obese' });
  });

  it('retourne null pour une taille nulle (division par zéro évitée)', () => {
    expect(calculateImc({ weightKg: 70, heightCm: 0 })).toBeNull();
  });

  it('retourne null pour un poids nul', () => {
    expect(calculateImc({ weightKg: 0, heightCm: 175 })).toBeNull();
  });

  it('retourne null pour des valeurs négatives', () => {
    expect(calculateImc({ weightKg: -70, heightCm: 175 })).toBeNull();
    expect(calculateImc({ weightKg: 70, heightCm: -175 })).toBeNull();
  });

  it('retourne null pour NaN et Infinity', () => {
    expect(calculateImc({ weightKg: Number.NaN, heightCm: 175 })).toBeNull();
    expect(calculateImc({ weightKg: 70, heightCm: Number.POSITIVE_INFINITY })).toBeNull();
  });

  it('gère des valeurs extrêmes sans produire Infinity', () => {
    const result = calculateImc({ weightKg: 1e6, heightCm: 1 });
    expect(result).not.toBeNull();
    expect(Number.isFinite(result!.imc)).toBe(true);
  });
});

describe('classifyImc', () => {
  it('applique les bornes OMS', () => {
    expect(classifyImc(18.4)).toBe('underweight');
    expect(classifyImc(18.5)).toBe('normal');
    expect(classifyImc(24.9)).toBe('normal');
    expect(classifyImc(25)).toBe('overweight');
    expect(classifyImc(29.9)).toBe('overweight');
    expect(classifyImc(30)).toBe('obese');
  });
});
