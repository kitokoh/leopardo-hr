import { isFiniteNumber, safeDivide } from '../../lib/validation';

/**
 * Répartition d'un coût de conteneur entre plusieurs lignes de marchandise.
 *
 * Trois clés de répartition au choix — c'est le choix qui compte :
 *   - `cbm`    : au prorata du volume (marchandise légère, taxée au volume)
 *   - `weight` : au prorata du poids (marchandise dense)
 *   - `value`  : au prorata de la valeur (assurance, frais financiers)
 *
 * Formule par ligne : quote-part = coût total × (clé de la ligne / somme des clés)
 *
 * Hypothèses : coût total ≥ 0 ; chaque ligne a une clé strictement positive ;
 * la somme des clés est strictement positive. Une clé nulle est REFUSÉE plutôt
 * que traitée comme 0 % : une ligne sans volume ni poids ni valeur n'a pas sa
 * place dans une répartition, et l'ignorer silencieusement fausserait le total.
 *
 * Résultat INDICATIF : la répartition réelle dépend du contrat transporteur
 * (poids minimum facturable, arrondis, palettes).
 */

export type AllocationBasis = 'cbm' | 'weight' | 'value';

export interface AllocationLine {
  /** Identifiant libre de la ligne (référence produit, n° de colis…). */
  id: string;
  /** Volume en m³. */
  cbm: number;
  /** Poids en kg. */
  weight: number;
  /** Valeur déclarée. */
  value: number;
}

export interface AllocationResultLine {
  id: string;
  /** Clé utilisée pour cette ligne. */
  basisValue: number;
  /** Part de la ligne dans la somme des clés, en points de pourcentage. */
  sharePercent: number;
  /** Montant alloué à la ligne. */
  allocated: number;
}

export interface AllocationResult {
  basis: AllocationBasis;
  totalCost: number;
  /** Somme des clés (contrôle de cohérence). */
  basisTotal: number;
  lines: AllocationResultLine[];
  /** Somme des montants alloués — doit égaler `totalCost` à l'arrondi près. */
  allocatedTotal: number;
}

/**
 * Répartit un coût total entre des lignes selon une clé.
 * Retourne `null` si le coût est non fini ou négatif, si la liste est vide, si
 * une clé est non finie ou ≤ 0, ou si la somme des clés est nulle.
 */
export function allocateContainerCost(
  totalCost: number,
  lines: AllocationLine[],
  basis: AllocationBasis,
): AllocationResult | null {
  if (!isFiniteNumber(totalCost) || totalCost < 0) return null;
  if (lines.length === 0) return null;

  const basisValues = lines.map((line) => line[basis]);

  for (const value of basisValues) {
    if (!isFiniteNumber(value) || value <= 0) return null;
  }

  const basisTotal = basisValues.reduce((sum, value) => sum + value, 0);

  if (!isFiniteNumber(basisTotal) || basisTotal <= 0) return null;

  const resultLines = lines.map((line, index) => {
    const basisValue = basisValues[index];
    const ratio = safeDivide(basisValue, basisTotal) ?? 0;

    return {
      id: line.id,
      basisValue,
      sharePercent: ratio * 100,
      allocated: totalCost * ratio,
    };
  });

  return {
    basis,
    totalCost,
    basisTotal,
    lines: resultLines,
    allocatedTotal: resultLines.reduce((sum, line) => sum + line.allocated, 0),
  };
}
