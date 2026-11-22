import { isFiniteNumber, safeDivide } from '../../lib/validation';

/**
 * Marge et prix cible à l'export.
 *
 * Formules :
 *   marge        = prix de vente − coût de revient
 *   taux de marge = marge / coût de revient   (markup, sur le COÛT)
 *   taux de marque = marge / prix de vente    (marge commerciale, sur le PV)
 *   coefficient  = prix de vente / coût de revient
 *   prix cible   = coût / (1 − taux de marque visé)
 *
 * ⚠️ La distinction marge/marque est la source d'erreur n°1 en commerce :
 * ajouter 30 % au coût ne donne PAS 30 % de marge sur le prix de vente
 * (30 % de markup = 23,08 % de marque). L'outil affiche les deux.
 *
 * Hypothèses : coût > 0, prix ≥ 0, taux de marque visé dans [0, 100[.
 */
export interface ExportMarginInput {
  /** Coût de revient unitaire (rendu, taxes incluses). */
  unitCost: number;
  /** Prix de vente unitaire. */
  unitPrice: number;
}

export interface ExportMarginResult {
  /** Marge unitaire en valeur. */
  margin: number;
  /** Taux de marge sur le coût (markup), en points de pourcentage. */
  markupPercent: number;
  /**
   * Taux de marque sur le prix de vente, en points de pourcentage.
   *
   * `null` quand le prix de vente est nul : la division est indéfinie. Afficher
   * « 0 % » dans ce cas serait faux — une vente à prix nul n'a pas un taux de
   * marque nul, elle n'en a aucun.
   */
  marginRatePercent: number | null;
  /** Coefficient multiplicateur (PV / coût). */
  coefficient: number;
}

/**
 * Calcule marge, taux de marge, taux de marque et coefficient.
 * Retourne `null` si le coût est non fini ou ≤ 0, ou si le prix est non fini
 * ou négatif.
 */
export function computeExportMargin(input: ExportMarginInput): ExportMarginResult | null {
  const { unitCost, unitPrice } = input;

  if (!isFiniteNumber(unitCost) || unitCost <= 0) return null;
  if (!isFiniteNumber(unitPrice) || unitPrice < 0) return null;

  const margin = unitPrice - unitCost;
  const markup = safeDivide(margin, unitCost);
  const marginRate = safeDivide(margin, unitPrice);
  const coefficient = safeDivide(unitPrice, unitCost);

  return {
    margin,
    markupPercent: (markup ?? 0) * 100,
    // `null` (et non 0) quand le prix est nul : le taux est indéfini.
    marginRatePercent: marginRate === null ? null : marginRate * 100,
    coefficient: coefficient ?? 0,
  };
}

/**
 * Prix de vente nécessaire pour atteindre un taux de marque visé.
 *
 * `prix = coût / (1 − taux/100)` — la formule inverse du taux de marque.
 * Retourne `null` si le coût est non fini ou ≤ 0, ou si le taux sort de
 * [0, 100[ (à 100 % le prix serait infini : c'est une erreur de saisie, pas
 * un résultat).
 */
export function targetPriceForMarginRate(unitCost: number, marginRatePercent: number): number | null {
  if (!isFiniteNumber(unitCost) || unitCost <= 0) return null;
  if (!isFiniteNumber(marginRatePercent) || marginRatePercent < 0 || marginRatePercent >= 100) return null;

  return unitCost / (1 - marginRatePercent / 100);
}
