/**
 * Helpers de validation d'entrées numériques — partagés par tous les outils.
 *
 * Règle du module : aucune formule ne reçoit une valeur non finie. Les cas
 * limites (vide, NaN, Infinity, division par zéro, hors bornes) sont traités
 * ICI, une fois, plutôt que dispersés dans chaque outil.
 */

/** Convertit une saisie texte en nombre fini, ou `null` si invalide. */
export function parseNumber(raw: string): number | null {
  if (typeof raw !== 'string') return null;
  const trimmed = raw.trim().replace(',', '.');
  if (trimmed === '') return null;
  const value = Number(trimmed);
  return Number.isFinite(value) ? value : null;
}

/** Vrai si la valeur est un nombre fini (rejette NaN, Infinity, null, undefined). */
export function isFiniteNumber(value: unknown): value is number {
  return typeof value === 'number' && Number.isFinite(value);
}

/** Borne une valeur dans [min, max]. */
export function clamp(value: number, min: number, max: number): number {
  return Math.min(Math.max(value, min), max);
}

/** Vrai si la valeur est dans [min, max] (bornes optionnelles). */
export function isWithinRange(value: number, min?: number, max?: number): boolean {
  if (!isFiniteNumber(value)) return false;
  if (min !== undefined && value < min) return false;
  if (max !== undefined && value > max) return false;
  return true;
}

/**
 * Division protégée : retourne `null` si le diviseur est nul (ou non fini),
 * au lieu de produire `Infinity`/`NaN`.
 */
export function safeDivide(numerator: number, denominator: number): number | null {
  if (!isFiniteNumber(numerator) || !isFiniteNumber(denominator)) return null;
  if (denominator === 0) return null;
  return numerator / denominator;
}

/** Arrondit à `digits` décimales (arrondi au plus proche, demi vers le haut). */
export function round(value: number, digits = 2): number {
  if (!isFiniteNumber(value)) return 0;
  const factor = 10 ** digits;
  return Math.round((value + Number.EPSILON) * factor) / factor;
}
