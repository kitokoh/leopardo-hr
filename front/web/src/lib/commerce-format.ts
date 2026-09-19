/**
 * commerce-format.ts — helpers purs de l'espace vendeur Commerce (BC-17
 * RETAIL, #7675).
 *
 * Le backend Retail travaille EXCLUSIVEMENT en minor units (entiers, pattern
 * Catalog #6880) : `price_minor`, `amount_minor`, `opening_cash_minor`…
 * Ces helpers font la conversion affichage/saisie (2 décimales, pattern
 * `/100` du POS restaurant) et portent la logique pure des mouvements de
 * stock (mapping `reason_code` → direction, delta signé envoyé à
 * `POST /retail/stock/movements`). Testés dans
 * `src/lib/__tests__/commerce-format.test.ts`.
 */

import type { AppLocale } from '@/lib/i18n';

/** Formate un montant en minor units : `1250` → `12,50 XOF` (locale fr). */
export function formatMinor(locale: AppLocale, amountMinor: number, currency: string): string {
  const major = (amountMinor / 100).toLocaleString(locale, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
  return `${major} ${currency}`;
}

/**
 * Convertit une saisie en unités majeures (« 12.50 » ou « 12,50 ») en minor
 * units entières (`1250`). Retourne `null` si la saisie n'est pas un nombre.
 */
export function parseMajorToMinor(input: string): number | null {
  const normalized = input.trim().replace(',', '.');
  if (normalized === '' || !/^-?\d+(\.\d+)?$/.test(normalized)) {
    return null;
  }
  return Math.round(Number(normalized) * 100);
}

/** Codes motifs des mouvements de stock (miroir de RetailStockReasonCode). */
export const STOCK_REASON_CODES = [
  'purchase',
  'sale',
  'adjustment',
  'return',
  'transfer_in',
  'transfer_out',
  'loss',
] as const;

export type StockReasonCode = (typeof STOCK_REASON_CODES)[number];

export type StockDirection = 'in' | 'out' | 'signed';

/**
 * Direction d'un motif de mouvement : entrée (`purchase`, `return`,
 * `transfer_in`), sortie (`sale`, `transfer_out`, `loss`) ou ajustement
 * signé (`adjustment`, le signe est laissé à l'opérateur).
 */
export function reasonDirection(reason: StockReasonCode): StockDirection {
  switch (reason) {
    case 'purchase':
    case 'return':
    case 'transfer_in':
      return 'in';
    case 'sale':
    case 'transfer_out':
    case 'loss':
      return 'out';
    case 'adjustment':
      return 'signed';
  }
}

/**
 * Delta signé envoyé au backend (`quantity_delta`) : quantité saisie
 * POSITIVE + motif → signe déduit de la direction ; un ajustement garde le
 * signe saisi tel quel.
 */
export function signedQuantityDelta(reason: StockReasonCode, quantity: number): number {
  const direction = reasonDirection(reason);
  if (direction === 'signed') {
    return quantity;
  }
  const magnitude = Math.abs(quantity);
  return direction === 'out' ? -magnitude : magnitude;
}
