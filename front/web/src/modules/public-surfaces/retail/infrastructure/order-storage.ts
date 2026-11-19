/**
 * Persistance locale des commandes — verticale Retail.
 *
 * Repris de `front/marketplace/src/lib/orders.ts` (#9627).
 *
 * Deux clés, deux rôles :
 *
 * - `leopardo_marche_orders` : commandes confirmées (référence + jeton de
 *   suivi), affichées sur la confirmation et réutilisables sur `/suivi` ;
 * - `leopardo_marche_checkout_keys` : clés d'idempotence par boutique, créées
 *   au premier envoi et conservées pour qu'une re-soumission (réseau coupé,
 *   double clic) rejoue la **même** commande côté serveur.
 *
 * Les noms de clés sont conservés à l'identique : un panier ou une commande en
 * cours survit à la bascule depuis `front/marketplace`.
 */

import type { CreatedOrder, SavedOrder } from '../domain/checkout';

/** Clé des commandes confirmées. */
export const ORDERS_STORAGE_KEY = 'leopardo_marche_orders';

/** Clé des clés d'idempotence en cours. */
export const CHECKOUT_KEYS_STORAGE_KEY = 'leopardo_marche_checkout_keys';

/** Nombre de commandes conservées localement. */
export const MAX_SAVED_ORDERS = 20;

function isBrowser(): boolean {
  return typeof window !== 'undefined';
}

/** Vrai si l'entrée a la forme d'une commande enregistrée. */
function isSavedOrder(entry: unknown): entry is SavedOrder {
  if (typeof entry !== 'object' || entry === null) {
    return false;
  }

  const order = entry as Partial<SavedOrder>;

  return (
    typeof order.reference === 'string' &&
    typeof order.trackingToken === 'string' &&
    typeof order.totalMinor === 'number' &&
    typeof order.currency === 'string' &&
    typeof order.sellerName === 'string' &&
    typeof order.sellerSlug === 'string' &&
    typeof order.createdAt === 'string'
  );
}

/** Lit les commandes enregistrées ; une entrée corrompue est écartée. */
export function readSavedOrders(): SavedOrder[] {
  if (!isBrowser()) {
    return [];
  }

  try {
    const raw = window.localStorage.getItem(ORDERS_STORAGE_KEY);

    if (!raw) {
      return [];
    }

    const parsed: unknown = JSON.parse(raw);

    return Array.isArray(parsed) ? parsed.filter(isSavedOrder) : [];
  } catch {
    return [];
  }
}

/**
 * Enregistre des commandes, les plus récentes en tête.
 *
 * La déduplication se fait par référence : rejouer une soumission idempotente
 * ne crée pas de doublon dans l'historique local.
 */
export function saveOrders(orders: readonly CreatedOrder[]): void {
  if (!isBrowser() || orders.length === 0) {
    return;
  }

  try {
    const existing = readSavedOrders();
    const references = new Set(orders.map((order) => order.reference));
    const merged = [...orders, ...existing.filter((order) => !references.has(order.reference))];

    window.localStorage.setItem(
      ORDERS_STORAGE_KEY,
      JSON.stringify(merged.slice(0, MAX_SAVED_ORDERS)),
    );
  } catch {
    // Stockage indisponible : la confirmation affiche quand même les jetons.
  }
}

/**
 * Retrouve une commande par sa référence.
 *
 * Utilisé au retour du PSP : le jeton de suivi n'est **jamais** transmis au
 * prestataire de paiement, il est relu localement.
 */
export function findSavedOrder(reference: string): SavedOrder | null {
  return readSavedOrders().find((order) => order.reference === reference) ?? null;
}

function readCheckoutKeys(): Record<string, string> {
  if (!isBrowser()) {
    return {};
  }

  try {
    const raw = window.localStorage.getItem(CHECKOUT_KEYS_STORAGE_KEY);

    if (!raw) {
      return {};
    }

    const parsed: unknown = JSON.parse(raw);

    return typeof parsed === 'object' && parsed !== null && !Array.isArray(parsed)
      ? (parsed as Record<string, string>)
      : {};
  } catch {
    return {};
  }
}

function writeCheckoutKeys(keys: Record<string, string>): void {
  if (!isBrowser()) {
    return;
  }

  try {
    window.localStorage.setItem(CHECKOUT_KEYS_STORAGE_KEY, JSON.stringify(keys));
  } catch {
    // Sans gravité : la clé sera simplement régénérée.
  }
}

/**
 * Rend la clé d'idempotence d'une boutique, en la créant si absente.
 *
 * La clé est **persistée avant le premier envoi** : c'est ce qui rend un
 * retry sûr. Si le stockage est indisponible, on rend une clé éphémère — le
 * retry perd son idempotence mais la commande reste possible.
 */
export function idempotencyKeyFor(sellerSlug: string): string {
  const keys = readCheckoutKeys();
  const existing = keys[sellerSlug];

  if (typeof existing === 'string' && existing.length > 0) {
    return existing;
  }

  const key = crypto.randomUUID();
  keys[sellerSlug] = key;
  writeCheckoutKeys(keys);

  return key;
}

/** Libère la clé d'une boutique une fois sa commande créée. */
export function releaseIdempotencyKey(sellerSlug: string): void {
  const keys = readCheckoutKeys();

  if (!(sellerSlug in keys)) {
    return;
  }

  delete keys[sellerSlug];
  writeCheckoutKeys(keys);
}
