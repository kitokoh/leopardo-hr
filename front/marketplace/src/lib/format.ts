/**
 * Formatage — Leopardo Marché.
 * Les montants circulent en minor units (centimes) : conversion + affichage fr-FR.
 */

/** Devises sans subdivision décimale (ISO 4217, exposant 0). */
const ZERO_DECIMAL_CURRENCIES = new Set([
  "XOF",
  "XAF",
  "JPY",
  "KRW",
  "VND",
  "GNF",
  "RWF",
  "UGX",
  "BIF",
  "DJF",
  "KMF",
  "MGA",
  "CLP",
]);

function minorUnitDivisor(currency: string): number {
  return ZERO_DECIMAL_CURRENCIES.has(currency.toUpperCase()) ? 1 : 100;
}

/** 250000 minor + "DZD" → « 2 500,00 DA » (formaté fr-FR). */
export function formatPrice(priceMinor: number, currency: string): string {
  const amount = priceMinor / minorUnitDivisor(currency);
  try {
    return new Intl.NumberFormat("fr-FR", {
      style: "currency",
      currency: currency.toUpperCase(),
      currencyDisplay: "narrowSymbol",
    }).format(amount);
  } catch {
    // Code devise non ISO : on formate le nombre et on appose le code.
    return `${new Intl.NumberFormat("fr-FR", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    }).format(amount)} ${currency}`;
  }
}

/** Date ISO → « 19 septembre 2026, 14:05 » (fr-FR), ou null si invalide. */
export function formatDateTime(iso: string | null | undefined): string | null {
  if (!iso) return null;
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) return null;
  return new Intl.DateTimeFormat("fr-FR", {
    dateStyle: "long",
    timeStyle: "short",
  }).format(date);
}
