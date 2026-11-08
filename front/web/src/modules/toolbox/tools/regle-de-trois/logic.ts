import { isFiniteNumber, safeDivide } from '../../lib/validation';

/**
 * Règle de trois (proportionnalité directe).
 *
 * Formule : si A correspond à B, alors C correspond à X avec
 *   X = B × C ÷ A
 * (équivalent à X = C × (B ÷ A) : on applique le même rapport B/A à C.)
 *
 * Unités : A, B et C sont des nombres dans des unités quelconques ; le résultat
 * est exprimé dans l'unité de B. Aucune conversion d'unité n'est faite ici.
 *
 * Hypothèses : proportionnalité DIRECTE uniquement (pas de règle de trois
 * inverse, qui relèverait d'un autre mode).
 *
 * Cas limite : A = 0 rend la proportion indéfinie → `null` (division par zéro).
 * A, B ou C non fini (NaN, Infinity) → `null`.
 */
export function ruleOfThree(a: number, b: number, c: number): number | null {
  if (!isFiniteNumber(a) || !isFiniteNumber(b) || !isFiniteNumber(c)) return null;

  const ratio = safeDivide(b, a);
  if (ratio === null) return null;

  return ratio * c;
}

/**
 * Règle de trois inverse (proportionnalité inverse).
 *
 * Formule : si A correspond à B, alors C correspond à X avec
 *   X = A × B ÷ C
 * (le produit A × B est constant : doubler C divise X par deux.)
 *
 * Cas limite : C = 0 → `null` (division par zéro).
 */
export function inverseRuleOfThree(a: number, b: number, c: number): number | null {
  if (!isFiniteNumber(a) || !isFiniteNumber(b) || !isFiniteNumber(c)) return null;

  const product = a * b;
  if (!isFiniteNumber(product)) return null;

  return safeDivide(product, c);
}
