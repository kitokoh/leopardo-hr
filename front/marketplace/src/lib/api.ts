/**
 * Client API centralisé — Leopardo Marché.
 *
 * Toutes les routes publiques `/api/v1/public/market/*` passent par ici :
 * types stricts, timeout, normalisation défensive de la pagination Laravel
 * (forme `{ data, meta: {...} }` OU forme aplatie `{ data, current_page, ... }`).
 */

const DEFAULT_BASE = "https://gestionemployerbackend.onrender.com";

export function apiBase(): string {
  const base = process.env.NEXT_PUBLIC_MARKET_API_BASE?.trim();
  return `${(base && base.length > 0 ? base : DEFAULT_BASE).replace(/\/+$/, "")}/api/v1`;
}

/* ── Types publics (DTO du contrat §3, MARKETPLACE_RETAIL_PUBLIC.md) ────── */

export interface PublicSellerRef {
  name: string;
  slug: string;
  city: string | null;
}

export interface PublicCategory {
  id: number;
  name: string;
}

export interface PublicProduct {
  id: number;
  name: string;
  description: string | null;
  price_minor: number;
  currency: string;
  image_url: string | null;
  category: PublicCategory | null;
  seller: PublicSellerRef;
  available: boolean;
}

export interface PublicSeller {
  name: string;
  slug: string;
  city: string | null;
  description: string | null;
  products_count: number;
}

export type ProductSort = "recent" | "price_asc" | "price_desc";

export interface ProductsQuery {
  q?: string;
  seller?: string;
  category?: string;
  min_price?: string;
  max_price?: string;
  sort?: ProductSort;
  page?: number;
  per_page?: number;
}

export interface Paginated<T> {
  data: T[];
  page: number;
  lastPage: number;
  total: number;
}

export type FulfillmentStatus =
  | "pending"
  | "confirmed"
  | "ready"
  | "shipped"
  | "delivered"
  | "cancelled";

export interface OrderPayload {
  seller: string;
  items: { product_id: number; quantity: number }[];
  customer: { name: string; phone: string; email?: string };
  delivery: { address: string; city: string; notes?: string };
  payment_method: "cash";
  idempotency_key: string;
}

export interface OrderCreated {
  reference: string;
  tracking_token: string;
  total_minor: number;
  currency: string;
  seller: string;
}

export interface TimelineEntry {
  status: FulfillmentStatus;
  at: string | null;
}

export interface TrackedItem {
  name: string;
  quantity: number;
  unit_price_minor?: number;
  line_total_minor?: number;
}

export interface OrderTracking {
  reference: string;
  fulfillment_status: FulfillmentStatus;
  timeline: TimelineEntry[];
  items: TrackedItem[];
  total_minor: number;
  currency: string;
  seller?: PublicSellerRef | string;
}

/* ── Erreurs & fetch ────────────────────────────────────────────────────── */

export class ApiError extends Error {
  readonly status: number;
  readonly payload: unknown;

  constructor(message: string, status: number, payload?: unknown) {
    super(message);
    this.name = "ApiError";
    this.status = status;
    this.payload = payload;
  }
}

interface RequestOptions {
  method?: "GET" | "POST";
  body?: unknown;
  query?: Record<string, string | number | undefined>;
  timeoutMs?: number;
  headers?: Record<string, string>;
}

async function request<T>(path: string, options: RequestOptions = {}): Promise<T> {
  const { method = "GET", body, query, timeoutMs = 12_000, headers } = options;

  const url = new URL(`${apiBase()}${path}`);
  if (query) {
    for (const [key, value] of Object.entries(query)) {
      if (value !== undefined && `${value}`.length > 0) {
        url.searchParams.set(key, `${value}`);
      }
    }
  }

  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), timeoutMs);

  let response: Response;
  try {
    response = await fetch(url.toString(), {
      method,
      headers: {
        Accept: "application/json",
        ...(body !== undefined ? { "Content-Type": "application/json" } : {}),
        ...(headers ?? {}),
      },
      body: body !== undefined ? JSON.stringify(body) : undefined,
      cache: "no-store",
      signal: controller.signal,
    });
  } catch (error) {
    const aborted = error instanceof DOMException && error.name === "AbortError";
    throw new ApiError(
      aborted
        ? "Le serveur met trop de temps à répondre. Veuillez réessayer."
        : "Impossible de joindre le serveur. Vérifiez votre connexion.",
      0,
    );
  } finally {
    clearTimeout(timer);
  }

  let payload: unknown = null;
  try {
    payload = await response.json();
  } catch {
    payload = null;
  }

  if (!response.ok) {
    const message =
      (isRecord(payload) && typeof payload.message === "string" && payload.message) ||
      defaultErrorMessage(response.status);
    throw new ApiError(message, response.status, payload);
  }

  return payload as T;
}

function defaultErrorMessage(status: number): string {
  if (status === 404) return "Ressource introuvable.";
  if (status === 422) return "Les informations envoyées sont invalides.";
  if (status === 429) return "Trop de requêtes. Merci de patienter un instant.";
  return "Une erreur est survenue. Veuillez réessayer.";
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === "object" && value !== null && !Array.isArray(value);
}

/* ── Normalisation de la pagination Laravel ─────────────────────────────── */

function toNumber(value: unknown, fallback: number): number {
  const n = typeof value === "string" ? Number(value) : value;
  return typeof n === "number" && Number.isFinite(n) ? n : fallback;
}

function normalizePaginated<T>(payload: unknown): Paginated<T> {
  if (!isRecord(payload)) {
    return { data: [], page: 1, lastPage: 1, total: 0 };
  }
  const data = Array.isArray(payload.data) ? (payload.data as T[]) : [];
  const meta = isRecord(payload.meta) ? payload.meta : payload;
  return {
    data,
    page: toNumber(meta.current_page, 1),
    lastPage: toNumber(meta.last_page, 1),
    total: toNumber(meta.total, data.length),
  };
}

/* ── Endpoints ──────────────────────────────────────────────────────────── */

export async function fetchProducts(query: ProductsQuery = {}): Promise<Paginated<PublicProduct>> {
  const payload = await request<unknown>("/public/market/products", {
    query: {
      q: query.q,
      seller: query.seller,
      category: query.category,
      min_price: query.min_price,
      max_price: query.max_price,
      sort: query.sort,
      page: query.page,
      per_page: query.per_page,
    },
  });
  return normalizePaginated<PublicProduct>(payload);
}

export async function fetchProduct(id: string | number): Promise<PublicProduct> {
  const payload = await request<unknown>(`/public/market/products/${id}`);
  // Certaines ressources Laravel enveloppent la réponse dans `data`.
  if (isRecord(payload) && isRecord(payload.data)) {
    return payload.data as unknown as PublicProduct;
  }
  return payload as PublicProduct;
}

/** L'API peut renvoyer `name` ou `shop_name` — on normalise sur `name`. */
function normalizeSeller(raw: unknown): PublicSeller {
  const record = isRecord(raw) ? raw : {};
  const name =
    (typeof record.name === "string" && record.name) ||
    (typeof record.shop_name === "string" && record.shop_name) ||
    "Boutique";
  return {
    name,
    slug: typeof record.slug === "string" ? record.slug : "",
    city: typeof record.city === "string" ? record.city : null,
    description: typeof record.description === "string" ? record.description : null,
    products_count: toNumber(record.products_count, 0),
  };
}

export async function fetchSellers(): Promise<PublicSeller[]> {
  const payload = await request<unknown>("/public/market/sellers");
  const list = Array.isArray(payload)
    ? payload
    : isRecord(payload) && Array.isArray(payload.data)
      ? payload.data
      : [];
  return list.map(normalizeSeller).filter((seller) => seller.slug.length > 0);
}

export async function fetchSeller(slug: string): Promise<PublicSeller> {
  const payload = await request<unknown>(`/public/market/sellers/${encodeURIComponent(slug)}`);
  const raw = isRecord(payload) && isRecord(payload.data) ? payload.data : payload;
  return normalizeSeller(raw);
}

export async function createOrder(
  payload: OrderPayload,
  authToken?: string | null,
): Promise<OrderCreated> {
  const response = await request<unknown>("/public/market/orders", {
    method: "POST",
    body: payload,
    timeoutMs: 20_000,
    // #7814 — acheteur connecté : la commande est rattachée à son compte
    // (token OPTIONNEL, le checkout invité reste inchangé).
    ...(authToken ? { headers: { Authorization: `Bearer ${authToken}` } } : {}),
  });
  const raw = isRecord(response) && isRecord(response.data) ? response.data : response;
  return raw as OrderCreated;
}

const FULFILLMENT_STATUSES: FulfillmentStatus[] = [
  "pending",
  "confirmed",
  "ready",
  "shipped",
  "delivered",
  "cancelled",
];

function isFulfillmentStatus(value: unknown): value is FulfillmentStatus {
  return typeof value === "string" && (FULFILLMENT_STATUSES as string[]).includes(value);
}

/** Timeline défensive : tableau `[{status, at}]` OU objet `{status: date}`. */
function normalizeTimeline(raw: unknown): TimelineEntry[] {
  if (Array.isArray(raw)) {
    return raw.flatMap((entry) => {
      if (!isRecord(entry) || !isFulfillmentStatus(entry.status)) return [];
      return [{ status: entry.status, at: typeof entry.at === "string" ? entry.at : null }];
    });
  }
  if (isRecord(raw)) {
    return Object.entries(raw).flatMap(([status, at]) =>
      isFulfillmentStatus(status) ? [{ status, at: typeof at === "string" ? at : null }] : [],
    );
  }
  return [];
}

export async function fetchOrderTracking(reference: string, token: string): Promise<OrderTracking> {
  const payload = await request<unknown>(
    `/public/market/orders/${encodeURIComponent(reference)}`,
    { query: { token } },
  );
  const raw = isRecord(payload) && isRecord(payload.data) ? payload.data : payload;
  const record = isRecord(raw) ? raw : {};

  const items: TrackedItem[] = Array.isArray(record.items)
    ? record.items.flatMap((item) => {
        if (!isRecord(item)) return [];
        return [
          {
            name: typeof item.name === "string" ? item.name : "Article",
            quantity: toNumber(item.quantity, 1),
            unit_price_minor:
              item.unit_price_minor !== undefined
                ? toNumber(item.unit_price_minor, 0)
                : undefined,
            line_total_minor:
              item.line_total_minor !== undefined
                ? toNumber(item.line_total_minor, 0)
                : undefined,
          },
        ];
      })
    : [];

  return {
    reference: typeof record.reference === "string" ? record.reference : reference,
    fulfillment_status: isFulfillmentStatus(record.fulfillment_status)
      ? record.fulfillment_status
      : "pending",
    timeline: normalizeTimeline(record.timeline),
    items,
    total_minor: toNumber(record.total_minor, 0),
    currency: typeof record.currency === "string" ? record.currency : "DZD",
    seller: isRecord(record.seller)
      ? (record.seller as unknown as PublicSellerRef)
      : typeof record.seller === "string"
        ? record.seller
        : undefined,
  };
}
