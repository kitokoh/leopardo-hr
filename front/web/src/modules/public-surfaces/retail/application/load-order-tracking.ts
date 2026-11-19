/**
 * Cas d'usage — verticale Retail : le suivi de commande.
 *
 * Charge le suivi public et prépare la timeline affichable (étapes franchies,
 * libellés). Ne lève jamais.
 */

import { getSurfaceCopy } from '../../copy/surfaces-copy';
import { formatPrice } from '../domain/product';
import { reachedSteps, type FulfillmentStatus, type OrderTracking } from '../domain/order';
import { loadOrderTracking } from '../infrastructure/market-gateway';

/** Étape de timeline prête à l'affichage. */
export interface TimelineStepView {
  readonly status: FulfillmentStatus;
  readonly label: string;
  readonly at: string | null;
  readonly reached: boolean;
}

/** Suivi prêt à l'affichage. */
export interface OrderTrackingView {
  readonly reference: string;
  readonly status: FulfillmentStatus;
  readonly statusLabel: string;
  readonly totalLabel: string | null;
  readonly items: readonly { name: string; quantity: number; lineTotalLabel: string | null }[];
  readonly steps: readonly TimelineStepView[];
  readonly updatedAt: string | null;
}

/**
 * Libellés de statut, depuis le catalogue de copie.
 *
 * Le code de statut vient de l'API : on ne l'affiche jamais brut. Un code
 * absent de la table retombe sur le code lui-même plutôt que sur une chaîne
 * vide — un statut inconnu doit rester visible pour être signalé.
 */
function statusLabel(status: FulfillmentStatus, locale?: string): string {
  const shared = getSurfaceCopy(locale).shared;

  const labels: Record<FulfillmentStatus, string> = {
    pending: shared.scheduleTbc,
    confirmed: shared.bookable,
    ready: shared.bookable,
    shipped: shared.bookable,
    delivered: shared.bookable,
    cancelled: shared.soldOut,
  };

  return labels[status] ?? status;
}

/** Compose l'affichage d'un suivi. */
export function buildTrackingView(
  tracking: OrderTracking,
  locale = 'fr-FR',
): OrderTrackingView {
  const reached = new Set(reachedSteps(tracking.status));

  return {
    reference: tracking.reference,
    status: tracking.status,
    statusLabel: statusLabel(tracking.status, locale),
    totalLabel: formatPrice(tracking.totalMinor, tracking.currency, locale),
    items: tracking.items.map((item) => ({
      name: item.name,
      quantity: item.quantity,
      lineTotalLabel: formatPrice(item.lineTotalMinor, tracking.currency, locale),
    })),
    steps: (['pending', 'confirmed', 'ready', 'shipped', 'delivered'] as FulfillmentStatus[]).map(
      (status) => ({
        status,
        label: statusLabel(status, locale),
        at: tracking.timeline.find((entry) => entry.status === status)?.at ?? null,
        reached: reached.has(status),
      }),
    ),
    updatedAt: tracking.updatedAt,
  };
}

/** Charge un suivi ; `null` si la référence/le jeton ne donne rien. */
export async function loadTrackingView(
  reference: string,
  token: string,
  locale = 'fr-FR',
): Promise<OrderTrackingView | null> {
  const tracking = await loadOrderTracking(reference, token);

  return tracking === null ? null : buildTrackingView(tracking, locale);
}
