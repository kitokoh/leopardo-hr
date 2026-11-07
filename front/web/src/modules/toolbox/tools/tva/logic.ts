import { isFiniteNumber, round, safeDivide } from '../../lib/validation';

/**
 * TVA — calcul HT / TTC.
 *
 * Formules :
 *   - HT → TTC : TTC = HT × (1 + taux) ; TVA = TTC − HT
 *   - TTC → HT : HT = TTC ÷ (1 + taux) ; TVA = TTC − HT
 * Unités : montants dans une devise quelconque (le calcul est neutre), taux en
 * pourcentage (20 = 20 %).
 * Hypothèses : taux unique appliqué au montant ; aucun arrondi intermédiaire
 * (l'arrondi n'a lieu qu'à l'affichage).
 * Référence : impots.gouv.fr — taux de TVA français.
 */

export type VatDirection = 'ht-to-ttc' | 'ttc-to-ht';

export interface VatInput {
  amount: number;
  ratePercent: number;
  direction: VatDirection;
}

export interface VatResult {
  ht: number;
  vat: number;
  ttc: number;
}

/**
 * Calcule la TVA. Retourne `null` si un montant ou un taux est non fini ou
 * négatif. Le diviseur `(1 + taux)` est toujours ≥ 1 pour un taux ≥ 0 : aucune
 * division par zéro n'est possible.
 */
export function calculateVat(input: VatInput): VatResult | null {
  const { amount, ratePercent, direction } = input;
  if (!isFiniteNumber(amount) || !isFiniteNumber(ratePercent)) return null;
  if (amount < 0 || ratePercent < 0) return null;

  const rate = ratePercent / 100;

  if (direction === 'ht-to-ttc') {
    const ht = amount;
    const ttc = ht * (1 + rate);
    return { ht: round(ht, 2), vat: round(ttc - ht, 2), ttc: round(ttc, 2) };
  }

  const ttc = amount;
  const ht = safeDivide(ttc, 1 + rate);
  if (ht === null) return null;
  return { ht: round(ht, 2), vat: round(ttc - ht, 2), ttc: round(ttc, 2) };
}
