/**
 * Domaine public — verticale Retail (Marché) : la commande et son suivi.
 *
 * Repris de `front/marketplace/src/lib/orders.ts` et de la timeline de
 * `src/app/suivi/page.tsx` (#9627). Le statut de fulfillment et sa timeline
 * sont des **règles** : l'ordre des étapes, ce qui est terminal, ce qui est
 * encore en cours. Ils vivent ici, pas dans un composant.
 */

/** Statuts de fulfillment servis par l'API publique. */
export const FULFILLMENT_STATUSES = [
  'pending',
  'confirmed',
  'ready',
  'shipped',
  'delivered',
  'cancelled',
] as const;

export type FulfillmentStatus = (typeof FULFILLMENT_STATUSES)[number];

/** Une étape datée de la timeline. */
export interface TimelineEntry {
  readonly status: FulfillmentStatus;
  readonly at: string | null;
}

/** Une commande telle que le suivi public la rend. */
export interface OrderTracking {
  readonly reference: string;
  readonly status: FulfillmentStatus;
  /**
   * Statut du paiement (`pending` | `paid` | `refunded` | `failed`).
   *
   * Distinct du statut de fulfillment : une commande peut être payée et pas
   * encore expédiée, ou livrée et remboursée. La page de retour de paiement
   * (#7812) ne peut pas se contenter du fulfillment.
   */
  readonly paymentStatus: string;
  readonly totalMinor: number;
  readonly currency: string;
  readonly items: readonly TrackedItem[];
  readonly timeline: readonly TimelineEntry[];
  readonly updatedAt: string | null;
}

/** Une ligne d'une commande suivie. */
export interface TrackedItem {
  readonly name: string;
  readonly quantity: number;
  readonly lineTotalMinor: number;
}

/** Vrai si la valeur est un statut de fulfillment connu. */
export function isFulfillmentStatus(value: unknown): value is FulfillmentStatus {
  return typeof value === 'string' && (FULFILLMENT_STATUSES as readonly string[]).includes(value);
}

/**
 * Ordre canonique du parcours.
 *
 * `cancelled` en est absent : c'est une sortie, pas une étape. La placer dans
 * la séquence ferait afficher « annulée » comme une progression.
 */
export const FULFILLMENT_SEQUENCE: readonly FulfillmentStatus[] = [
  'pending',
  'confirmed',
  'ready',
  'shipped',
  'delivered',
];

/** Vrai si la commande est terminée (livrée ou annulée). */
export function isTerminal(status: FulfillmentStatus): boolean {
  return status === 'delivered' || status === 'cancelled';
}

/**
 * Position d'un statut dans le parcours, ou `-1` s'il en sort (annulée).
 *
 * Sert à marquer les étapes franchies : une étape dont l'index est inférieur
 * ou égal à celui du statut courant est atteinte.
 */
export function sequenceIndex(status: FulfillmentStatus): number {
  return FULFILLMENT_SEQUENCE.indexOf(status);
}

/**
 * Étapes franchies pour un statut donné.
 *
 * Une commande annulée ne prétend **pas** avoir progressé : on rend un
 * ensemble vide plutôt que de marquer les étapes antérieures comme atteintes,
 * ce qui laisserait croire à une livraison partielle.
 */
export function reachedSteps(status: FulfillmentStatus): readonly FulfillmentStatus[] {
  if (status === 'cancelled') {
    return [];
  }

  const index = sequenceIndex(status);

  return index < 0 ? [] : FULFILLMENT_SEQUENCE.slice(0, index + 1);
}

/**
 * Normalise une timeline reçue de l'API.
 *
 * Deux formes circulent : un tableau `[{status, at}]` et un objet
 * `{status: date}`. Les entrées inconnues sont écartées — une timeline qui
 * afficherait un statut brut serait pire que pas de timeline.
 */
export function normalizeTimeline(raw: unknown): TimelineEntry[] {
  if (Array.isArray(raw)) {
    return raw.flatMap((entry) => {
      if (typeof entry !== 'object' || entry === null) {
        return [];
      }

      const record = entry as { status?: unknown; at?: unknown };

      if (!isFulfillmentStatus(record.status)) {
        return [];
      }

      return [{ status: record.status, at: typeof record.at === 'string' ? record.at : null }];
    });
  }

  if (typeof raw === 'object' && raw !== null) {
    return Object.entries(raw as Record<string, unknown>).flatMap(([status, at]) =>
      isFulfillmentStatus(status)
        ? [{ status, at: typeof at === 'string' ? at : null }]
        : [],
    );
  }

  return [];
}
