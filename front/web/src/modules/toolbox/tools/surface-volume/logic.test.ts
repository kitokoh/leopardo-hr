import { computeShape } from './logic';

/**
 * Tests surfaces et volumes — cas connus calculés à la main + cas limites.
 * π est la constante Math.PI : les résultats sont comparés avec toBeCloseTo.
 */
describe('computeShape — formes planes', () => {
  it('carré de côté 4 : aire 16', () => {
    expect(computeShape('carre', { a: 4 })).toEqual({ area: 16, volume: null });
  });

  it('rectangle 5 × 3 : aire 15', () => {
    expect(computeShape('rectangle', { length: 5, width: 3 })).toEqual({ area: 15, volume: null });
  });

  it('triangle base 6 hauteur 4 : aire 12', () => {
    expect(computeShape('triangle', { base: 6, height: 4 })).toEqual({ area: 12, volume: null });
  });

  it('cercle de rayon 2 : aire 4π', () => {
    const result = computeShape('cercle', { radius: 2 });
    expect(result?.area).toBeCloseTo(4 * Math.PI, 10);
    expect(result?.volume).toBeNull();
  });
});

describe('computeShape — solides', () => {
  it('cube de côté 3 : volume 27, aire totale 54', () => {
    expect(computeShape('cube', { a: 3 })).toEqual({ area: 54, volume: 27 });
  });

  it('pavé droit 2 × 3 × 4 : volume 24, aire totale 52', () => {
    // 2 × (2×3 + 2×4 + 3×4) = 2 × (6 + 8 + 12) = 52
    expect(computeShape('pave-droit', { length: 2, width: 3, height: 4 })).toEqual({
      area: 52,
      volume: 24,
    });
  });

  it('cylindre rayon 2 hauteur 5 : volume 20π, aire latérale 20π', () => {
    const result = computeShape('cylindre', { radius: 2, height: 5 });
    expect(result?.volume).toBeCloseTo(20 * Math.PI, 10);
    expect(result?.area).toBeCloseTo(20 * Math.PI, 10);
  });

  it('sphère de rayon 3 : volume 36π, aire 36π', () => {
    const result = computeShape('sphere', { radius: 3 });
    expect(result?.volume).toBeCloseTo(36 * Math.PI, 10);
    expect(result?.area).toBeCloseTo(36 * Math.PI, 10);
  });
});

describe('computeShape — cas limites', () => {
  it('retourne null pour une dimension nulle', () => {
    expect(computeShape('carre', { a: 0 })).toBeNull();
    expect(computeShape('pave-droit', { length: 2, width: 0, height: 4 })).toBeNull();
  });

  it('retourne null pour une dimension négative', () => {
    expect(computeShape('cercle', { radius: -2 })).toBeNull();
    expect(computeShape('cube', { a: -3 })).toBeNull();
  });

  it('retourne null pour une dimension manquante', () => {
    expect(computeShape('rectangle', { length: 5 })).toBeNull();
    expect(computeShape('triangle', { base: 6 })).toBeNull();
  });

  it('retourne null pour NaN et Infinity', () => {
    expect(computeShape('carre', { a: Number.NaN })).toBeNull();
    expect(computeShape('sphere', { radius: Number.POSITIVE_INFINITY })).toBeNull();
  });

  it('retourne null pour une forme inconnue', () => {
    expect(computeShape('losange' as never, { a: 1 })).toBeNull();
  });
});
