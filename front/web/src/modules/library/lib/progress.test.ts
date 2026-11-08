import {
  clampProgress,
  progressPercent,
  mergeProgress,
  type ReadingProgress,
} from '../lib/progress';

/**
 * Tests du cœur de progression de lecture (fonctions pures).
 */
describe('progress — clampProgress', () => {
  it('borne la page dans [1, total]', () => {
    expect(clampProgress(0, 10)).toBe(1);
    expect(clampProgress(-5, 10)).toBe(1);
    expect(clampProgress(15, 10)).toBe(10);
    expect(clampProgress(5, 10)).toBe(5);
  });

  it('accepte toute page ≥ 1 si le total est inconnu', () => {
    expect(clampProgress(42, 0)).toBe(42);
    expect(clampProgress(0, 0)).toBe(1);
  });

  it('gère les valeurs non finies', () => {
    expect(clampProgress(NaN, 10)).toBe(1);
    expect(clampProgress(Infinity, 10)).toBe(10);
  });
});

describe('progress — progressPercent', () => {
  it('calcule un pourcentage arrondi', () => {
    expect(progressPercent(5, 10)).toBe(50);
    expect(progressPercent(1, 3)).toBe(33);
    expect(progressPercent(10, 10)).toBe(100);
  });

  it('retourne 0 si le total est inconnu', () => {
    expect(progressPercent(5, 0)).toBe(0);
  });

  it('borne le pourcentage à 100', () => {
    expect(progressPercent(999, 10)).toBe(100);
  });
});

describe('progress — mergeProgress', () => {
  const base: ReadingProgress[] = [
    { slug: 'a', page: 3, totalPages: 10, updatedAt: 1000 },
    { slug: 'b', page: 1, totalPages: 5, updatedAt: 2000 },
  ];

  it('ajoute une nouvelle entrée en tête', () => {
    const merged = mergeProgress(base, { slug: 'c', page: 2, totalPages: 8, updatedAt: 3000 });
    expect(merged[0].slug).toBe('c');
    expect(merged).toHaveLength(3);
  });

  it('remplace l’entrée existante du même slug', () => {
    const merged = mergeProgress(base, { slug: 'a', page: 7, totalPages: 10, updatedAt: 3000 });
    expect(merged).toHaveLength(2);
    expect(merged.find((item) => item.slug === 'a')!.page).toBe(7);
  });

  it('trie par date de mise à jour décroissante', () => {
    const merged = mergeProgress(base, { slug: 'c', page: 2, totalPages: 8, updatedAt: 500 });
    expect(merged.map((item) => item.slug)).toEqual(['b', 'a', 'c']);
  });
});
