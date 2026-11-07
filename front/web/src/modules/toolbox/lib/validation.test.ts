import { clamp, isFiniteNumber, isWithinRange, parseNumber, round, safeDivide } from './validation';

/**
 * Tests des helpers de validation — socle anti-erreur de tous les outils.
 */
describe('parseNumber', () => {
  it('parse un entier et un décimal', () => {
    expect(parseNumber('42')).toBe(42);
    expect(parseNumber('3.5')).toBe(3.5);
  });

  it('accepte la virgule décimale', () => {
    expect(parseNumber('3,5')).toBe(3.5);
  });

  it('retourne null pour une chaîne vide ou invalide', () => {
    expect(parseNumber('')).toBeNull();
    expect(parseNumber('   ')).toBeNull();
    expect(parseNumber('abc')).toBeNull();
  });

  it('retourne null pour Infinity', () => {
    expect(parseNumber('Infinity')).toBeNull();
  });
});

describe('isFiniteNumber', () => {
  it('accepte les nombres finis', () => {
    expect(isFiniteNumber(0)).toBe(true);
    expect(isFiniteNumber(-1.5)).toBe(true);
  });

  it('rejette NaN, Infinity et non-nombres', () => {
    expect(isFiniteNumber(Number.NaN)).toBe(false);
    expect(isFiniteNumber(Number.POSITIVE_INFINITY)).toBe(false);
    expect(isFiniteNumber('1')).toBe(false);
    expect(isFiniteNumber(null)).toBe(false);
  });
});

describe('safeDivide', () => {
  it('divise normalement', () => {
    expect(safeDivide(10, 4)).toBe(2.5);
  });

  it('retourne null pour une division par zéro', () => {
    expect(safeDivide(10, 0)).toBeNull();
  });

  it('retourne null pour des entrées non finies', () => {
    expect(safeDivide(Number.NaN, 2)).toBeNull();
    expect(safeDivide(10, Number.POSITIVE_INFINITY)).toBeNull();
  });
});

describe('clamp / isWithinRange', () => {
  it('borne une valeur', () => {
    expect(clamp(15, 0, 10)).toBe(10);
    expect(clamp(-5, 0, 10)).toBe(0);
    expect(clamp(5, 0, 10)).toBe(5);
  });

  it('vérifie l’appartenance à un intervalle', () => {
    expect(isWithinRange(5, 0, 10)).toBe(true);
    expect(isWithinRange(11, 0, 10)).toBe(false);
    expect(isWithinRange(Number.NaN, 0, 10)).toBe(false);
  });
});

describe('round', () => {
  it('arrondit à n décimales', () => {
    expect(round(2.345, 2)).toBe(2.35);
    expect(round(2.344, 2)).toBe(2.34);
    expect(round(22.857, 1)).toBe(22.9);
  });

  it('retourne 0 pour une entrée non finie', () => {
    expect(round(Number.NaN)).toBe(0);
  });
});
