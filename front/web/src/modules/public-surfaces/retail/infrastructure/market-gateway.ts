/**
 * Adaptateur réseau — verticale Retail : le contrat public `/public/market/*`.
 *
 * Repris de `front/marketplace/src/lib/api.ts` (#9627). C'est la **seule**
 * couture vers l'API marchande : le mapping est explicite champ par champ, donc
 * un renommage côté API casse ici et pas dans le rendu.
 *
 * ## Différence assumée avec l'app d'origine
 *
 * `front/marketplace` exigeait `NEXT_PUBLIC_MARKET_API_BASE` et construisait sa
 * propre base. Ici on réutilise `resolveSurfaceApiBaseUrl()` — la source de vérité
 * unique du front `web` (fail-fast en production, repli local en dev). Une
 * seconde convention d'URL dans le même dépôt était précisément la dérive que
 * la garde de parité combat.
 *
 * ## Contrat de panne
 *
 * Chaque lecture rend `null` quand l'API ne répond pas, jamais une liste vide :
 * une vitrine en panne ne doit pas ressembler à un catalogue vide.
 */

import { resolveSurfaceApiBaseUrl } from '../../kernel/infrastructure/api-base';

import type { Product } from '../domain/product';
import type { OrderTracking, TrackedItem } from '../domain/order';
import { normalizeTimeline } from '../domain/order';

/** Forme brute d'un produit dans le contrat public. */
interface RawProduct {
  id?: number;
  name?: string;
  description?: string | null;
  price_minor?: number;
  currency?: string;
  image_url?: string | null;
  available?: boolean;
  rating_avg?: number | null;
  rating_count?: number;
  seller?: { slug?: string; name?: string; shop_name?: string; city?: string | null };
}

/** Forme brute d'une commande suivie. */
interface RawOrder {
  reference?: string;
  status?: string;
  /** Statut du paiement — distinct du fulfillment (cf. `OrderTracking`). */
  payment_status?: string;
  total_minor?: number;
  currency?: string;
  items?: unknown;
  timeline?: unknown;
  updated_at?: string | null;
}

/** Traduit un produit brut vers le modèle de la vitrine. */
export function toProduct(raw: RawProduct): Product {
  return {
    id: typeof raw.id === 'number' ? raw.id : 0,
    name: typeof raw.name === 'string' ? raw.name : '',
    description: typeof raw.description === 'string' ? raw.description : null,
    priceMinor: typeof raw.price_minor === 'number' ? raw.price_minor : 0,
    currency: typeof raw.currency === 'string' ? raw.currency : 'EUR',
    imageUrl: typeof raw.image_url === 'string' ? raw.image_url : null,
    seller: {
      slug: typeof raw.seller?.slug === 'string' ? raw.seller.slug : '',
      // L'API rend `name` ou `shop_name` selon la ressource : on normalise.
      name:
        (typeof raw.seller?.name === 'string' && raw.seller.name) ||
        (typeof raw.seller?.shop_name === 'string' && raw.seller.shop_name) ||
        '',
      city: typeof raw.seller?.city === 'string' ? raw.seller.city : null,
    },
    available: raw.available !== false,
    rating: typeof raw.rating_avg === 'number' ? raw.rating_avg : null,
    ratingCount: typeof raw.rating_count === 'number' ? raw.rating_count : 0,
  };
}

/** Résultat paginé normalisé (Laravel rend deux formes selon la ressource). */
export interface ProductPage {
  readonly items: readonly Product[];
  readonly page: number;
  readonly lastPage: number;
  readonly total: number;
}

function toNumber(value: unknown, fallback: number): number {
  return typeof value === 'number' && Number.isFinite(value) ? value : fallback;
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}

/**
 * Recherche des produits.
 *
 * `null` = API indisponible. Un catalogue vide est un résultat légitime et se
 * distingue par `items.length === 0` avec `null` non rendu.
 */
export async function searchProducts(params: {
  q?: string;
  seller?: string;
  category?: string;
  minPrice?: number;
  maxPrice?: number;
  sort?: string;
  page?: number;
  perPage?: number;
}): Promise<ProductPage | null> {
  const query = new URLSearchParams();

  if (params.q) query.set('q', params.q);
  if (params.seller) query.set('seller', params.seller);
  if (params.category) query.set('category', params.category);
  if (params.minPrice !== undefined) query.set('min_price', String(params.minPrice));
  if (params.maxPrice !== undefined) query.set('max_price', String(params.maxPrice));
  if (params.sort) query.set('sort', params.sort);
  if (params.page && params.page > 1) query.set('page', String(params.page));
  if (params.perPage) query.set('per_page', String(params.perPage));

  const qs = query.toString();

  try {
    const response = await fetch(
      `${resolveSurfaceApiBaseUrl()}/public/market/products${qs ? `?${qs}` : ''}`,
      { headers: { Accept: 'application/json' }, next: { revalidate: 60 } },
    );

    if (!response.ok) {
      return null;
    }

    const payload: unknown = await response.json();
    const record = isRecord(payload) ? payload : {};
    const data = Array.isArray(record.data) ? record.data : [];
    const meta = isRecord(record.meta) ? record.meta : record;

    return {
      items: data.map((entry) => toProduct(entry as RawProduct)),
      page: toNumber(meta.current_page, 1),
      lastPage: toNumber(meta.last_page, 1),
      total: toNumber(meta.total, data.length),
    };
  } catch {
    return null;
  }
}

/** Charge un produit par identifiant ; `null` si absent ou API indisponible. */
export async function loadProduct(id: string | number): Promise<Product | null> {
  try {
    const response = await fetch(
      `${resolveSurfaceApiBaseUrl()}/public/market/products/${encodeURIComponent(String(id))}`,
      { headers: { Accept: 'application/json' }, next: { revalidate: 60 } },
    );

    if (!response.ok) {
      return null;
    }

    const payload: unknown = await response.json();
    const record = isRecord(payload) ? payload : {};
    const raw = isRecord(record.data) ? record.data : record;

    return toProduct(raw as RawProduct);
  } catch {
    return null;
  }
}

/** Réponse brute de création d'une commande. */
interface RawCreatedOrder {
  reference?: string;
  tracking_token?: string;
  total_minor?: number;
  currency?: string;
  payment?: { checkout_url?: string | null };
}

/**
 * Crée une commande pour **une** boutique.
 *
 * Lève sur échec : l'appelant (le cas d'usage de checkout) doit distinguer une
 * boutique servie d'une boutique en échec, et un `null` silencieux ferait
 * passer une commande perdue pour un succès.
 */
export async function createOrder(payload: {
  seller: string;
  items: readonly { product_id: number; quantity: number }[];
  customer: { name: string; phone: string; email?: string };
  delivery: { address: string; city: string; notes?: string };
  payment_method: string;
  idempotency_key: string;
}): Promise<{
  reference: string;
  trackingToken: string;
  totalMinor: number;
  currency: string;
  checkoutUrl: string | null;
}> {
  const response = await fetch(`${resolveSurfaceApiBaseUrl()}/public/market/orders`, {
    method: 'POST',
    headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
    // Session acheteur optionnelle : si le cookie est présent, la commande est
    // rattachée au compte (historique + avis vérifiés) ; sinon checkout invité.
    credentials: 'include',
    cache: 'no-store',
  });

  const text = await response.text();
  let parsed: unknown = null;

  if (text) {
    try {
      parsed = JSON.parse(text) as unknown;
    } catch {
      parsed = null;
    }
  }

  if (!response.ok) {
    const message =
      isRecord(parsed) && typeof parsed.message === 'string'
        ? parsed.message
        : `HTTP ${response.status}`;

    throw new Error(message);
  }

  const record = isRecord(parsed) ? parsed : {};
  const raw = (isRecord(record.data) ? record.data : record) as RawCreatedOrder;

  return {
    reference: typeof raw.reference === 'string' ? raw.reference : '',
    trackingToken: typeof raw.tracking_token === 'string' ? raw.tracking_token : '',
    totalMinor: toNumber(raw.total_minor, 0),
    currency: typeof raw.currency === 'string' ? raw.currency : 'EUR',
    checkoutUrl:
      typeof raw.payment?.checkout_url === 'string' ? raw.payment.checkout_url : null,
  };
}

/** Charge le suivi d'une commande par référence + jeton. */
export async function loadOrderTracking(
  reference: string,
  token: string,
): Promise<OrderTracking | null> {
  try {
    const response = await fetch(
      `${resolveSurfaceApiBaseUrl()}/public/market/orders/${encodeURIComponent(reference)}?token=${encodeURIComponent(token)}`,
      { headers: { Accept: 'application/json' }, cache: 'no-store' },
    );

    if (!response.ok) {
      return null;
    }

    const payload: unknown = await response.json();
    const record = isRecord(payload) ? payload : {};
    const raw = (isRecord(record.data) ? record.data : record) as RawOrder;

    const items: TrackedItem[] = Array.isArray(raw.items)
      ? raw.items.flatMap((entry) => {
          if (!isRecord(entry)) {
            return [];
          }

          return [
            {
              name: typeof entry.name === 'string' ? entry.name : '',
              quantity: toNumber(entry.quantity, 0),
              lineTotalMinor: toNumber(entry.line_total_minor, 0),
            },
          ];
        })
      : [];

    return {
      reference: typeof raw.reference === 'string' ? raw.reference : reference,
      status: (typeof raw.status === 'string' ? raw.status : 'pending') as OrderTracking['status'],
      paymentStatus: typeof raw.payment_status === 'string' ? raw.payment_status : 'pending',
      totalMinor: toNumber(raw.total_minor, 0),
      currency: typeof raw.currency === 'string' ? raw.currency : 'EUR',
      items,
      timeline: normalizeTimeline(raw.timeline),
      updatedAt: typeof raw.updated_at === 'string' ? raw.updated_at : null,
    };
  } catch {
    return null;
  }
}
