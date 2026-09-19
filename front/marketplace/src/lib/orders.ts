/**
 * Persistance locale des commandes — Leopardo Marché.
 *
 * - `leopardo_marche_orders` : commandes confirmées (référence + jeton de
 *   suivi), affichées sur /confirmation et réutilisables sur /suivi ;
 * - `leopardo_marche_checkout_keys` : clés d'idempotence par boutique, créées
 *   au premier envoi du checkout et conservées pour qu'une re-soumission
 *   (réseau coupé, double clic) rejoue la MÊME commande côté serveur.
 */

export const ORDERS_STORAGE_KEY = "leopardo_marche_orders";
export const CHECKOUT_KEYS_STORAGE_KEY = "leopardo_marche_checkout_keys";

export interface SavedOrder {
  reference: string;
  trackingToken: string;
  totalMinor: number;
  currency: string;
  sellerName: string;
  sellerSlug: string;
  createdAt: string;
}

function isBrowser(): boolean {
  return typeof window !== "undefined";
}

export function readSavedOrders(): SavedOrder[] {
  if (!isBrowser()) return [];
  try {
    const raw = window.localStorage.getItem(ORDERS_STORAGE_KEY);
    if (!raw) return [];
    const parsed: unknown = JSON.parse(raw);
    if (!Array.isArray(parsed)) return [];
    return parsed.filter((entry): entry is SavedOrder => {
      if (typeof entry !== "object" || entry === null) return false;
      const order = entry as Partial<SavedOrder>;
      return (
        typeof order.reference === "string" &&
        typeof order.trackingToken === "string" &&
        typeof order.totalMinor === "number" &&
        typeof order.currency === "string" &&
        typeof order.sellerName === "string" &&
        typeof order.sellerSlug === "string" &&
        typeof order.createdAt === "string"
      );
    });
  } catch {
    return [];
  }
}

export function saveOrders(orders: SavedOrder[]): void {
  if (!isBrowser()) return;
  try {
    const existing = readSavedOrders();
    const references = new Set(orders.map((order) => order.reference));
    const merged = [...orders, ...existing.filter((order) => !references.has(order.reference))];
    // Garde les 20 commandes les plus récentes.
    window.localStorage.setItem(ORDERS_STORAGE_KEY, JSON.stringify(merged.slice(0, 20)));
  } catch {
    // Stockage indisponible : la confirmation affiche quand même les jetons.
  }
}

/**
 * Retourne la clé d'idempotence associée à une boutique pour le checkout en
 * cours — la crée (crypto.randomUUID) et la persiste si absente.
 */
export function idempotencyKeyFor(sellerSlug: string): string {
  const fallback = () => crypto.randomUUID();
  if (!isBrowser()) return fallback();
  try {
    const raw = window.localStorage.getItem(CHECKOUT_KEYS_STORAGE_KEY);
    const parsed: unknown = raw ? JSON.parse(raw) : {};
    const keys: Record<string, string> =
      typeof parsed === "object" && parsed !== null && !Array.isArray(parsed)
        ? (parsed as Record<string, string>)
        : {};
    if (typeof keys[sellerSlug] === "string" && keys[sellerSlug].length > 0) {
      return keys[sellerSlug];
    }
    const key = fallback();
    keys[sellerSlug] = key;
    window.localStorage.setItem(CHECKOUT_KEYS_STORAGE_KEY, JSON.stringify(keys));
    return key;
  } catch {
    return fallback();
  }
}

/** Libère la clé d'idempotence d'une boutique une fois sa commande créée. */
export function releaseIdempotencyKey(sellerSlug: string): void {
  if (!isBrowser()) return;
  try {
    const raw = window.localStorage.getItem(CHECKOUT_KEYS_STORAGE_KEY);
    if (!raw) return;
    const parsed: unknown = JSON.parse(raw);
    if (typeof parsed !== "object" || parsed === null || Array.isArray(parsed)) return;
    const keys = parsed as Record<string, string>;
    delete keys[sellerSlug];
    window.localStorage.setItem(CHECKOUT_KEYS_STORAGE_KEY, JSON.stringify(keys));
  } catch {
    // Sans gravité : la clé sera simplement réutilisée (rejeu idempotent → 200).
  }
}
