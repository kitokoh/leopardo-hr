import { isFiniteNumber, safeDivide } from '../../lib/validation';

/**
 * Convertisseur de devises à taux de référence STATIQUES.
 *
 * ⚠️ Aucun appel réseau : les taux sont des constantes documentées, fournies
 * par l'appelant (voir `data/tool-options.ts`). Le résultat est une ESTIMATION
 * indicative, jamais un taux de marché en temps réel — l'interface doit
 * l'afficher comme telle (avertissement `estimate`).
 *
 * Modèle : les taux sont exprimés « 1 unité de la devise = X EUR ». On convertit
 * donc en passant par l'euro comme pivot :
 *   montant_eur = montant_source × taux_source
 *   montant_cible = montant_eur / taux_cible
 *
 * Cas limite : taux source ou cible <= 0 ou non fini → `null` ; montant non
 * fini → `null`.
 */
export function convertCurrency(
  amount: number,
  sourceRateToEur: number,
  targetRateToEur: number,
): number | null {
  if (!isFiniteNumber(amount) || !isFiniteNumber(sourceRateToEur) || !isFiniteNumber(targetRateToEur)) {
    return null;
  }
  if (sourceRateToEur <= 0 || targetRateToEur <= 0) return null;

  const inEur = amount * sourceRateToEur;
  if (!isFiniteNumber(inEur)) return null;

  return safeDivide(inEur, targetRateToEur);
}
