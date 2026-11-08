import { isFiniteNumber, safeDivide } from '../../lib/validation';

/**
 * Marge, taux de marque et prix de vente.
 *
 * Vocabulaire (à ne pas confondre) :
 *   - Taux de MARGE  = (PV − PA) ÷ PA   → rapporté au coût d'achat
 *   - Taux de MARQUE = (PV − PA) ÷ PV   → rapporté au prix de vente
 *   - Coefficient    = PV ÷ PA
 *
 * Formules :
 *   - marge (valeur)      : PV − PA
 *   - taux de marge       : (PV − PA) ÷ PA × 100
 *   - taux de marque      : (PV − PA) ÷ PV × 100
 *   - coefficient         : PV ÷ PA
 *   - PV depuis marge     : PA × (1 + tauxMarge ÷ 100)
 *   - PV depuis marque    : PA ÷ (1 − tauxMarque ÷ 100)
 *
 * Unités : montants dans une même devise ; taux en points de pourcentage.
 *
 * Cas limites : PA = 0 → taux de marge indéfini (`null`) ; PV = 0 → taux de
 * marque indéfini (`null`) ; taux de marque ≥ 100 % → PV indéfini (`null`,
 * division par zéro ou prix négatif) ; entrées non finies → `null`.
 */

export interface MarginInput {
  /** Prix d'achat / coût (HT). */
  cost: number;
  /** Prix de vente (HT). */
  price: number;
}

export interface MarginResult {
  /** Marge en valeur (PV − PA). */
  margin: number;
  /** Taux de marge en % (rapporté au coût) — `null` si PA = 0. */
  marginRate: number | null;
  /** Taux de marque en % (rapporté au prix de vente) — `null` si PV = 0. */
  markupRate: number | null;
  /** Coefficient multiplicateur PV ÷ PA — `null` si PA = 0. */
  coefficient: number | null;
}

/**
 * Calcule marge, taux de marge, taux de marque et coefficient.
 * Retourne `null` si un montant est non fini ou négatif.
 */
export function computeMargin(input: MarginInput): MarginResult | null {
  const { cost, price } = input;
  if (!isFiniteNumber(cost) || cost < 0) return null;
  if (!isFiniteNumber(price) || price < 0) return null;

  const margin = price - cost;
  const marginRate = safeDivide(margin, cost);
  const markupRate = safeDivide(margin, price);
  const coefficient = safeDivide(price, cost);

  return {
    margin,
    marginRate: marginRate === null ? null : marginRate * 100,
    markupRate: markupRate === null ? null : markupRate * 100,
    coefficient,
  };
}

/**
 * Prix de vente à partir du coût et d'un taux de MARGE (rapporté au coût).
 * PV = PA × (1 + taux ÷ 100). Retourne `null` si les entrées sont invalides.
 */
export function priceFromMarginRate(cost: number, marginRatePercent: number): number | null {
  if (!isFiniteNumber(cost) || cost < 0) return null;
  if (!isFiniteNumber(marginRatePercent)) return null;
  return cost * (1 + marginRatePercent / 100);
}

/**
 * Prix de vente à partir du coût et d'un taux de MARQUE (rapporté au PV).
 * PV = PA ÷ (1 − taux ÷ 100). Retourne `null` si le taux est ≥ 100 %
 * (prix infini ou négatif) ou si les entrées sont invalides.
 */
export function priceFromMarkupRate(cost: number, markupRatePercent: number): number | null {
  if (!isFiniteNumber(cost) || cost < 0) return null;
  if (!isFiniteNumber(markupRatePercent)) return null;
  if (markupRatePercent >= 100) return null;

  const divisor = 1 - markupRatePercent / 100;
  const price = safeDivide(cost, divisor);
  if (price === null || price < 0) return null;
  return price;
}
