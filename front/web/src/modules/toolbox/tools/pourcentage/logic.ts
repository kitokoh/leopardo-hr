import { isFiniteNumber, safeDivide } from '../../lib/validation';

/**
 * Pourcentages et évolution.
 *
 * Formules :
 *   - percent-of : A × B ÷ 100  (B % de A)
 *   - evolution  : (B − A) ÷ A × 100  (variation de A vers B, en %)
 *   - discount   : A × (1 − B ÷ 100)  (A diminué de B %)
 * Unités : A et B sont des nombres ; le résultat est un nombre (points de
 * pourcentage pour `evolution`, valeur pour les autres modes).
 * Cas limite : `evolution` avec A = 0 retourne `null` (division par zéro).
 */

export type PercentMode = 'percent-of' | 'evolution' | 'discount';

/**
 * Calcule selon le mode. Retourne `null` si une entrée est non finie, ou si le
 * mode `evolution` reçoit A = 0 (division par zéro).
 */
export function calculatePercent(mode: PercentMode, a: number, b: number): number | null {
  if (!isFiniteNumber(a) || !isFiniteNumber(b)) return null;

  switch (mode) {
    case 'percent-of':
      return (a * b) / 100;
    case 'evolution': {
      const ratio = safeDivide(b - a, a);
      return ratio === null ? null : ratio * 100;
    }
    case 'discount':
      return a * (1 - b / 100);
    default:
      return null;
  }
}
