import { allocateContainerCost, type AllocationLine } from './logic';

/**
 * Tests répartition conteneur — cas connus + limites.
 */
const LINES: AllocationLine[] = [
  { id: 'A', cbm: 10, weight: 1_000, value: 5_000 },
  { id: 'B', cbm: 30, weight: 3_000, value: 15_000 },
];

describe('allocateContainerCost', () => {
  it('répartit au prorata du volume', () => {
    // 10 / 40 = 25 % ; 30 / 40 = 75 % ; coût 4 000 → 1 000 et 3 000
    const result = allocateContainerCost(4_000, LINES, 'cbm');

    expect(result?.lines[0].sharePercent).toBeCloseTo(25, 10);
    expect(result?.lines[0].allocated).toBeCloseTo(1_000, 10);
    expect(result?.lines[1].sharePercent).toBeCloseTo(75, 10);
    expect(result?.lines[1].allocated).toBeCloseTo(3_000, 10);
  });

  it('répartit au prorata du poids', () => {
    // 1 000 / 4 000 = 25 % ; 3 000 / 4 000 = 75 %
    const result = allocateContainerCost(4_000, LINES, 'weight');

    expect(result?.lines[0].allocated).toBeCloseTo(1_000, 10);
    expect(result?.lines[1].allocated).toBeCloseTo(3_000, 10);
  });

  it('répartit au prorata de la valeur', () => {
    // 5 000 / 20 000 = 25 % ; 15 000 / 20 000 = 75 %
    const result = allocateContainerCost(4_000, LINES, 'value');

    expect(result?.lines[0].allocated).toBeCloseTo(1_000, 10);
    expect(result?.lines[1].allocated).toBeCloseTo(3_000, 10);
  });

  it('la somme allouée égale le coût total', () => {
    const result = allocateContainerCost(1_234.56, LINES, 'cbm');

    expect(result?.allocatedTotal).toBeCloseTo(1_234.56, 8);
  });

  it('accepte un coût nul (rien à répartir)', () => {
    const result = allocateContainerCost(0, LINES, 'cbm');

    expect(result?.allocatedTotal).toBe(0);
    expect(result?.lines[0].allocated).toBe(0);
  });

  it('retourne null sur une liste vide', () => {
    expect(allocateContainerCost(1_000, [], 'cbm')).toBeNull();
  });

  it('retourne null sur une clé nulle ou négative', () => {
    const withZero: AllocationLine[] = [
      { id: 'A', cbm: 0, weight: 1_000, value: 5_000 },
      { id: 'B', cbm: 30, weight: 3_000, value: 15_000 },
    ];

    // Une ligne sans volume n'a pas sa place dans une répartition au volume :
    // l'ignorer silencieusement fausserait le total.
    expect(allocateContainerCost(1_000, withZero, 'cbm')).toBeNull();
    expect(allocateContainerCost(1_000, withZero, 'weight')).not.toBeNull();
  });

  it('retourne null sur un coût négatif', () => {
    expect(allocateContainerCost(-1, LINES, 'cbm')).toBeNull();
  });

  it('retourne null sur NaN ou Infinity', () => {
    expect(allocateContainerCost(Number.NaN, LINES, 'cbm')).toBeNull();
    expect(allocateContainerCost(Number.POSITIVE_INFINITY, LINES, 'cbm')).toBeNull();

    const withNaN: AllocationLine[] = [
      { id: 'A', cbm: Number.NaN, weight: 1_000, value: 5_000 },
      { id: 'B', cbm: 30, weight: 3_000, value: 15_000 },
    ];

    expect(allocateContainerCost(1_000, withNaN, 'cbm')).toBeNull();
  });

  it('gère une ligne unique (100 %)', () => {
    const result = allocateContainerCost(500, [{ id: 'A', cbm: 5, weight: 10, value: 100 }], 'cbm');

    expect(result?.lines[0].sharePercent).toBeCloseTo(100, 10);
    expect(result?.lines[0].allocated).toBeCloseTo(500, 10);
  });
});
