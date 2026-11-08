import { isFiniteNumber, safeDivide } from '../../lib/validation';

/**
 * Taux horaire à partir d'un salaire mensuel brut.
 *
 * Formule : `taux horaire = brut mensuel / (heures par semaine × 52 / 12)`.
 * Le diviseur est le nombre moyen d'heures travaillées par mois sur l'année
 * (52 semaines), ce qui lisse les mois de longueur inégale.
 *
 * Entrées : brut mensuel (>= 0) et heures par semaine (> 0).
 *
 * Cas limite : heures par semaine = 0 → `null` (division par zéro) ; brut
 * négatif ou non fini → `null`.
 */
export function hourlyRate(monthlyGross: number, hoursPerWeek: number): number | null {
  if (!isFiniteNumber(monthlyGross) || !isFiniteNumber(hoursPerWeek)) return null;
  if (monthlyGross < 0 || hoursPerWeek <= 0) return null;

  const monthlyHours = (hoursPerWeek * 52) / 12;
  return safeDivide(monthlyGross, monthlyHours);
}

/**
 * Salaire mensuel brut à partir d'un taux horaire.
 *
 * Formule inverse : `brut mensuel = taux horaire × (heures par semaine × 52 / 12)`.
 *
 * Cas limite : taux négatif ou non fini → `null` ; heures par semaine <= 0 → `null`.
 */
export function monthlyGrossFromHourly(hourly: number, hoursPerWeek: number): number | null {
  if (!isFiniteNumber(hourly) || !isFiniteNumber(hoursPerWeek)) return null;
  if (hourly < 0 || hoursPerWeek <= 0) return null;

  return hourly * ((hoursPerWeek * 52) / 12);
}
