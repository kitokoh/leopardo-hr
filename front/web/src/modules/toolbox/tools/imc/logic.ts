import { isFiniteNumber, round, safeDivide } from '../../lib/validation';

/**
 * IMC — Indice de masse corporelle.
 *
 * Formule (OMS) : IMC = poids (kg) ÷ taille (m)².
 * Unités : poids en kilogrammes, taille en centimètres (convertie en mètres).
 * Hypothèses : l'IMC ne tient pas compte de la masse musculaire, de l'âge ni
 * de la morphologie — c'est un indicateur de dépistage, pas un diagnostic.
 * Référence : OMS — https://www.who.int/health-topics/obesity
 */

export type ImcCategory = 'underweight' | 'normal' | 'overweight' | 'obese';

export interface ImcInput {
  weightKg: number;
  heightCm: number;
}

export interface ImcResult {
  imc: number;
  category: ImcCategory;
}

/**
 * Calcule l'IMC. Retourne `null` si une entrée est non finie ou non positive
 * (poids/taille nuls ou négatifs, NaN, Infinity) — jamais de division par zéro.
 */
export function calculateImc(input: ImcInput): ImcResult | null {
  const { weightKg, heightCm } = input;
  if (!isFiniteNumber(weightKg) || !isFiniteNumber(heightCm)) return null;
  if (weightKg <= 0 || heightCm <= 0) return null;

  const heightM = heightCm / 100;
  const imc = safeDivide(weightKg, heightM * heightM);
  if (imc === null) return null;

  return { imc: round(imc, 1), category: classifyImc(imc) };
}

/** Classification OMS de l'IMC (bornes : 18,5 / 25 / 30). */
export function classifyImc(imc: number): ImcCategory {
  if (imc < 18.5) return 'underweight';
  if (imc < 25) return 'normal';
  if (imc < 30) return 'overweight';
  return 'obese';
}
