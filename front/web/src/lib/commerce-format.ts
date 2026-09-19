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

/**
 * Statuts de suivi (`fulfillment_status`) des commandes web « Boutique en
 * ligne » (BC-17 marketplace, #7810) — miroir de la machine d'états serveur
 * `pending → confirmed → ready → shipped → delivered` (+ `cancelled`
 * terminal, cf. docs/specifications/MARKETPLACE_RETAIL_PUBLIC.md §2.3).
 */
export const FULFILLMENT_STATUSES = [
  'pending',
  'confirmed',
  'ready',
  'shipped',
  'delivered',
  'cancelled',
] as const;

export type FulfillmentStatus = (typeof FULFILLMENT_STATUSES)[number];

/** Actions vendeur exposées par `POST /retail/online/orders/{id}/{action}`. */
export const FULFILLMENT_ACTIONS = ['confirm', 'ready', 'ship', 'deliver', 'cancel'] as const;

export type FulfillmentAction = (typeof FULFILLMENT_ACTIONS)[number];

/** Statut cible atteint après une action de suivi (affichage optimiste). */
export function fulfillmentTarget(action: FulfillmentAction): FulfillmentStatus {
  switch (action) {
    case 'confirm':
      return 'confirmed';
    case 'ready':
      return 'ready';
    case 'ship':
      return 'shipped';
    case 'deliver':
      return 'delivered';
    case 'cancel':
      return 'cancelled';
  }
}

/**
 * Actions proposées à l'opérateur pour un statut donné (miroir des
 * transitions serveur ; une transition périmée est de toute façon rejetée
 * en 422 `INVALID_TRANSITION`). Les statuts terminaux (`delivered`,
 * `cancelled`) n'offrent aucune action ; un statut inconnu non plus
 * (fail-closed).
 */
export function fulfillmentActions(status: string): FulfillmentAction[] {
  switch (status as FulfillmentStatus) {
    case 'pending':
      return ['confirm', 'cancel'];
    case 'confirmed':
      return ['ready', 'cancel'];
    case 'ready':
      return ['ship', 'cancel'];
    case 'shipped':
      return ['deliver', 'cancel'];
    case 'delivered':
    case 'cancelled':
      return [];
    default:
      return [];
  }
}

/** Transition valide ? (le serveur reste seul juge — pré-filtre UI.) */
export function canApplyFulfillmentAction(status: string, action: FulfillmentAction): boolean {
  return fulfillmentActions(status).includes(action);
}

/**
 * Paiement capturé d'une commande (payload `payments` du détail
 * `GET /retail/online/orders/{id}` — paiement en ligne marketplace #7812).
 */
export type OrderPayment = {
  method: string;
  amount_minor: number;
  currency: string;
  status: string;
  paid_at: string | null;
};

export type OrderPaymentStatus = 'paid' | 'partial' | 'cod';

/**
 * Statut d'encaissement d'une commande web (#7812) : `paid` quand les
 * paiements capturés couvrent le total, `partial` quand un acompte en ligne
 * existe, `cod` (paiement à la livraison) sinon — même définition que le
 * solde serveur (`RetailOnlinePaymentService::outstandingAmountMinor`).
 */
export function orderPaymentStatus(
  payments: OrderPayment[] | undefined,
  totalMinor: number,
): OrderPaymentStatus {
  const captured = (payments ?? [])
    .filter((payment) => payment.status === 'captured')
    .reduce((sum, payment) => sum + payment.amount_minor, 0);
  if (captured >= totalMinor && totalMinor > 0) {
    return 'paid';
  }
  return captured > 0 ? 'partial' : 'cod';
}
