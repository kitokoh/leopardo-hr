import { isFiniteNumber } from '../../lib/validation';

/**
 * Droits de douane et TVA à l'import — estimation indicative.
 *
 * Formules (base CIF = valeur marchandise + fret + assurance) :
 *   valeur CIF   = marchandise + fret + assurance
 *   droit        = CIF × taux de droit
 *   base TVA     = CIF + droit
 *   TVA          = base TVA × taux de TVA
 *   total taxes  = droit + TVA
 *   coût rendu   = CIF + total taxes
 *
 * ⚠️ ESTIMATION INDICATIVE. Ce calcul n'est ni une déclaration en douane ni
 * une garantie de conformité : les taux réels dépendent du classement HS, du
 * pays d'origine, des accords commerciaux et des règles locales. L'outil ne
 * devine JAMAIS un taux — il applique celui que l'utilisateur saisit.
 *
 * Hypothèses : montants ≥ 0, taux dans [0, 100] (points de pourcentage).
 */
export interface CustomsDutyInput {
  /** Valeur de la marchandise (hors fret et assurance). */
  goodsValue: number;
  /** Fret international inclus dans la base CIF. */
  freight: number;
  /** Assurance transport incluse dans la base CIF. */
  insurance: number;
  /** Taux de droit de douane, en points de pourcentage (ex. 12 pour 12 %). */
  dutyRatePercent: number;
  /** Taux de TVA à l'import, en points de pourcentage (ex. 20 pour 20 %). */
  vatRatePercent: number;
}

export interface CustomsDutyResult {
  /** Base CIF (marchandise + fret + assurance). */
  cifValue: number;
  /** Droit de douane calculé. */
  dutyAmount: number;
  /** Base d'imposition de la TVA (CIF + droit). */
  vatBase: number;
  /** TVA à l'import calculée. */
  vatAmount: number;
  /** Droit + TVA. */
  totalTaxes: number;
  /** Coût rendu hors dédouanement (CIF + taxes). */
  landedCost: number;
}

/**
 * Calcule droits et TVA à l'import.
 * Retourne `null` si un montant est non fini ou négatif, ou si un taux sort de
 * [0, 100].
 */
export function computeCustomsDuty(input: CustomsDutyInput): CustomsDutyResult | null {
  const { goodsValue, freight, insurance, dutyRatePercent, vatRatePercent } = input;

  for (const amount of [goodsValue, freight, insurance]) {
    if (!isFiniteNumber(amount) || amount < 0) return null;
  }

  for (const rate of [dutyRatePercent, vatRatePercent]) {
    if (!isFiniteNumber(rate) || rate < 0 || rate > 100) return null;
  }

  const cifValue = goodsValue + freight + insurance;
  const dutyAmount = cifValue * (dutyRatePercent / 100);
  const vatBase = cifValue + dutyAmount;
  const vatAmount = vatBase * (vatRatePercent / 100);
  const totalTaxes = dutyAmount + vatAmount;

  return {
    cifValue,
    dutyAmount,
    vatBase,
    vatAmount,
    totalTaxes,
    landedCost: cifValue + totalTaxes,
  };
}
