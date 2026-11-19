/**
 * Formatage monétaire partagé par les verticales.
 *
 * Repris de `front/marketplace/src/lib/format.ts` (#9627).
 *
 * ## Pourquoi ce n'est pas dans une verticale
 *
 * Retail, Billets et Mobilité affichent toutes des montants. Dupliquer la
 * règle dans trois domaines la ferait diverger — et c'est précisément le genre
 * d'écart qui produit « 12,50 € » ici et « 12.5 EUR » là.
 *
 * ## La règle qui compte
 *
 * Les montants circulent en **unité mineure** (centimes). Une devise sans
 * décimales (XOF, JPY, KRW…) ne doit pas être divisée par 100 : afficher
 * « 1 250,00 FCFA » pour 1 250 FCFA est faux d'un facteur 100.
 */

/** Devises dont l'unité mineure vaut l'unité majeure. */
const ZERO_DECIMAL_CURRENCIES = new Set([
  'BIF',
  'CLP',
  'DJF',
  'GNF',
  'ISK',
  'JPY',
  'KMF',
  'KRW',
  'PYG',
  'RWF',
  'UGX',
  'VND',
  'VUV',
  'XAF',
  'XOF',
  'XPF',
]);

/** Vrai si la devise n'a pas de sous-unité. */
export function hasZeroDecimals(currency: string): boolean {
  return ZERO_DECIMAL_CURRENCIES.has(currency.toUpperCase());
}

/**
 * Formate un montant en unité mineure.
 *
 * `null` ou `undefined` rendent `null` — l'appelant décide quoi afficher
 * (« prix à confirmer ») plutôt que de recevoir un « 0 » trompeur.
 */
export function formatPrice(
  amountMinor: number | null | undefined,
  currency = 'EUR',
  locale = 'fr-FR',
): string | null {
  if (amountMinor === null || amountMinor === undefined || !Number.isFinite(amountMinor)) {
    return null;
  }

  const divisor = hasZeroDecimals(currency) ? 1 : 100;
  const amount = amountMinor / divisor;

  try {
    return new Intl.NumberFormat(locale, {
      style: 'currency',
      currency,
      minimumFractionDigits: hasZeroDecimals(currency) ? 0 : 2,
      maximumFractionDigits: hasZeroDecimals(currency) ? 0 : 2,
    }).format(amount);
  } catch {
    // Devise inconnue d'Intl : repli lisible plutôt qu'une exception.
    return `${amount.toFixed(hasZeroDecimals(currency) ? 0 : 2)} ${currency}`;
  }
}
