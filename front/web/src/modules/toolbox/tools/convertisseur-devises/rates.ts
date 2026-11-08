/**
 * Taux de référence STATIQUES pour le convertisseur de devises.
 *
 * ⚠️ Aucun appel réseau, aucune API : ces constantes sont des taux indicatifs
 * figés à la date de référence ci-dessous. Ils servent à donner un ORDRE DE
 * GRANDEUR, jamais un taux de marché en temps réel — l'interface affiche
 * l'avertissement « estimation » et la date de référence.
 *
 * Modèle : `rateToEur` = valeur en EUR d'UNE unité de la devise.
 * Exemple : 1 DZD = 0,0069 EUR.
 *
 * Pour rafraîchir : mettre à jour `REFERENCE_DATE` et les taux, puis
 * incrémenter la version de l'outil dans `data/catalog.ts`.
 */

export interface CurrencyOption {
  /** Code ISO 4217. */
  code: string;
  /** Valeur en EUR d'une unité de cette devise. */
  rateToEur: number;
}

/** Date de référence des taux (ISO `YYYY-MM-DD`). */
export const REFERENCE_DATE = '2026-11-01';

export const CURRENCIES: CurrencyOption[] = [
  { code: 'EUR', rateToEur: 1 },
  { code: 'USD', rateToEur: 0.92 },
  { code: 'GBP', rateToEur: 1.17 },
  { code: 'CHF', rateToEur: 1.05 },
  { code: 'DZD', rateToEur: 0.0069 },
  { code: 'MAD', rateToEur: 0.092 },
  { code: 'TND', rateToEur: 0.29 },
  { code: 'TRY', rateToEur: 0.027 },
  { code: 'AED', rateToEur: 0.25 },
  { code: 'SAR', rateToEur: 0.245 },
];

/** Retourne le taux EUR d'un code devise, ou `null` si inconnu. */
export function rateFor(code: string): number | null {
  const found = CURRENCIES.find((currency) => currency.code === code);
  return found ? found.rateToEur : null;
}
