import { isFiniteNumber } from '../../lib/validation';

/**
 * TVA multi-taux — récapitulatif d'une facture à plusieurs lignes.
 *
 * Chaque ligne porte un montant HT et un taux de TVA. Le récapitulatif
 * regroupe les lignes PAR TAUX (comme sur une facture française) et calcule :
 *   - base HT par taux   : Σ montants HT des lignes de ce taux
 *   - TVA par taux       : base HT × taux ÷ 100
 *   - total HT           : Σ bases HT
 *   - total TVA          : Σ TVA
 *   - total TTC          : total HT + total TVA
 *
 * Unités : montants dans une même devise ; taux en points de pourcentage.
 *
 * Hypothèses : montants HT ≥ 0, taux ≥ 0. Les taux sont regroupés par valeur
 * exacte (20 et 20,0 forment une seule ligne). Résultat INDICATIF : il ne
 * remplace pas une facture ni une déclaration de TVA.
 */

export interface VatLine {
  /** Montant hors taxes de la ligne. */
  amountHt: number;
  /** Taux de TVA en points de pourcentage (ex. 20 pour 20 %). */
  ratePercent: number;
}

export interface VatRateGroup {
  ratePercent: number;
  baseHt: number;
  vat: number;
}

export interface VatRecap {
  /** Détail par taux, trié par taux croissant. */
  groups: VatRateGroup[];
  totalHt: number;
  totalVat: number;
  totalTtc: number;
}

/**
 * Calcule le récapitulatif TVA d'une liste de lignes.
 * Retourne `null` si la liste est vide, si un montant est non fini ou < 0, ou
 * si un taux est non fini ou < 0.
 */
export function computeVatRecap(lines: VatLine[]): VatRecap | null {
  if (!Array.isArray(lines) || lines.length === 0) return null;

  const byRate = new Map<number, number>();

  for (const line of lines) {
    if (!isFiniteNumber(line.amountHt) || line.amountHt < 0) return null;
    if (!isFiniteNumber(line.ratePercent) || line.ratePercent < 0) return null;

    byRate.set(line.ratePercent, (byRate.get(line.ratePercent) ?? 0) + line.amountHt);
  }

  const groups: VatRateGroup[] = [...byRate.entries()]
    .map(([ratePercent, baseHt]) => ({
      ratePercent,
      baseHt,
      vat: (baseHt * ratePercent) / 100,
    }))
    .sort((left, right) => left.ratePercent - right.ratePercent);

  const totalHt = groups.reduce((sum, group) => sum + group.baseHt, 0);
  const totalVat = groups.reduce((sum, group) => sum + group.vat, 0);

  return { groups, totalHt, totalVat, totalTtc: totalHt + totalVat };
}
