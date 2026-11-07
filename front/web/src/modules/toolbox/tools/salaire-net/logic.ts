import { isFiniteNumber, round } from '../../lib/validation';

/**
 * Salaire brut → net — ESTIMATION.
 *
 * Formule : net ≈ brut × (1 − taux de cotisations salariales).
 * Unités : montants mensuels dans une devise quelconque, taux en pourcentage.
 * Hypothèses : le taux de cotisations est FOURNI PAR L'UTILISATEUR (le taux
 * réel dépend du statut, du pays et de l'entreprise). Aucun barème fiscal n'est
 * codé en dur : ce module ne prétend pas calculer une paie légale.
 * ⚠️ Résultat indicatif — ne remplace pas un bulletin de paie officiel.
 */

export interface NetSalaryInput {
  grossMonthly: number;
  contributionRatePercent: number;
}

export interface NetSalaryResult {
  net: number;
  deductions: number;
}

/**
 * Estime le salaire net. Retourne `null` si le brut est non fini ou négatif, ou
 * si le taux est non fini ou hors de [0, 100].
 */
export function estimateNetSalary(input: NetSalaryInput): NetSalaryResult | null {
  const { grossMonthly, contributionRatePercent } = input;
  if (!isFiniteNumber(grossMonthly) || !isFiniteNumber(contributionRatePercent)) return null;
  if (grossMonthly < 0) return null;
  if (contributionRatePercent < 0 || contributionRatePercent > 100) return null;

  const rate = contributionRatePercent / 100;
  const deductions = grossMonthly * rate;
  const net = grossMonthly - deductions;

  return { net: round(net, 2), deductions: round(deductions, 2) };
}
