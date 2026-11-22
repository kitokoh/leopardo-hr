import { isFiniteNumber, safeDivide } from '../../lib/validation';

/**
 * Prix de revient à l'import — coût unitaire rendu.
 *
 * Formule :
 *   coût total = achat + fret + assurance + droits de douane + taxes + autres frais
 *   coût unitaire = coût total / quantité
 *
 * Unités : tous les montants dans la MÊME devise (l'outil ne convertit pas —
 * une conversion implicite à un taux inventé produirait un chiffre faux).
 *
 * Hypothèses : quantité strictement positive ; tous les postes ≥ 0. Un poste
 * laissé vide vaut 0, ce qui est explicite : l'utilisateur voit le total se
 * construire poste par poste.
 *
 * Résultat INDICATIF : le coût de revient réel dépend des frais effectivement
 * facturés (arrondis transporteur, frais bancaires, écarts de change).
 */
export interface ImportUnitCostInput {
  /** Valeur d'achat de la marchandise (FOB/EXW selon l'Incoterm). */
  purchase: number;
  /** Fret international. */
  freight: number;
  /** Assurance transport. */
  insurance: number;
  /** Droits de douane. */
  duty: number;
  /** Taxes à l'import (TVA, accises…). */
  taxes: number;
  /** Autres frais (transitaire, magasinage, inspection…). */
  otherFees: number;
  /** Quantité d'unités importées. */
  quantity: number;
}

export interface ImportUnitCostResult {
  /** Somme de tous les postes. */
  totalCost: number;
  /** Coût unitaire rendu. */
  unitCost: number;
  /** Part de chaque poste dans le total, en points de pourcentage. */
  breakdown: {
    purchase: number;
    freight: number;
    insurance: number;
    duty: number;
    taxes: number;
    otherFees: number;
  };
}

/**
 * Calcule le coût total et le coût unitaire rendu.
 * Retourne `null` si un poste est non fini ou négatif, ou si la quantité est
 * non finie ou ≤ 0.
 */
export function computeImportUnitCost(input: ImportUnitCostInput): ImportUnitCostResult | null {
  const { purchase, freight, insurance, duty, taxes, otherFees, quantity } = input;

  const costs = [purchase, freight, insurance, duty, taxes, otherFees];

  for (const cost of costs) {
    if (!isFiniteNumber(cost) || cost < 0) return null;
  }

  if (!isFiniteNumber(quantity) || quantity <= 0) return null;

  const totalCost = purchase + freight + insurance + duty + taxes + otherFees;
  const unitCost = totalCost / quantity;

  const share = (value: number): number => {
    const ratio = safeDivide(value, totalCost);
    return ratio === null ? 0 : ratio * 100;
  };

  return {
    totalCost,
    unitCost,
    breakdown: {
      purchase: share(purchase),
      freight: share(freight),
      insurance: share(insurance),
      duty: share(duty),
      taxes: share(taxes),
      otherFees: share(otherFees),
    },
  };
}
