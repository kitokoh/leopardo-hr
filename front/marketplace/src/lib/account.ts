"use client";

/**
 * Compte acheteur Leopardo Marché (#7814) — client API + session locale.
 *
 * Le token Sanctum du guard dédié `market_customer` vit en localStorage
 * (site public multi-onglets) ; toute mutation émet ACCOUNT_EVENT pour la
 * réactivité même onglet (pattern identique à lib/cart.ts).
 */

import { apiBase, ApiError } from "@/lib/api";

export const ACCOUNT_STORAGE_KEY = "leopardo_marche_account";
export const ACCOUNT_EVENT = "leopardo-marche:account";

export interface AccountProfile {
  id: number;
  name: string;
  email: string;
  phone: string | null;
  created_at: string | null;
}

export interface AccountSession {
  token: string;
  account: AccountProfile;
}

export interface AccountOrderItem {
  product_id: number;
  product_name: string;
  quantity: string | number;
  unit_price_minor: number;
  line_total_minor: number;
}

export interface AccountOrder {
  reference: string;
  fulfillment_status: string | null;
  total_minor: number;
  currency: string;
  tracking_token: string | null;
  created_at: string | null;
  items: AccountOrderItem[];
  seller: { name: string | null; slug: string | null; city: string | null };
}

export interface FavoriteProduct {
  id: number;
  name: string;
  price_minor: number;
  currency: string;
  image_url: string | null;
  available: boolean;
  seller: { name: string | null; slug: string | null };
}

export interface Favorite {
  id: number;
  target_type: "product" | "seller";
  product: FavoriteProduct | null;
  seller: { name: string | null; slug: string | null; city: string | null } | null;
  created_at: string | null;
}

export interface PublicReview {
  id: number;
  rating: number;
  comment: string | null;
  author: string | null;
  created_at: string | null;
}

export interface ReviewList {
  data: PublicReview[];
  rating: { average: number | null; count: number };
}

/* ── Session locale ─────────────────────────────────────────────────────── */

function isBrowser(): boolean {
  return typeof window !== "undefined";
}

export function readSession(): AccountSession | null {
  if (!isBrowser()) return null;
  try {
    const raw = window.localStorage.getItem(ACCOUNT_STORAGE_KEY);
    if (!raw) return null;
    const parsed: unknown = JSON.parse(raw);
    if (
      typeof parsed !== "object" ||
      parsed === null ||
      typeof (parsed as AccountSession).token !== "string" ||
      typeof (parsed as AccountSession).account !== "object"
    ) {
      return null;
    }
    return parsed as AccountSession;
  } catch {
    return null;
  }
}

export function accountToken(): string | null {
  return readSession()?.token ?? null;
}

function writeSession(session: AccountSession | null): void {
  if (!isBrowser()) return;
  try {
    if (session === null) {
      window.localStorage.removeItem(ACCOUNT_STORAGE_KEY);
    } else {
      window.localStorage.setItem(ACCOUNT_STORAGE_KEY, JSON.stringify(session));
    }
  } catch {
    // stockage indisponible (navigation privée) : session mémoire perdue
    // au rechargement, sans casser le parcours.
  }
  window.dispatchEvent(new CustomEvent(ACCOUNT_EVENT));
}

/* ── Requêtes authentifiées ─────────────────────────────────────────────── */

interface AuthRequestOptions {
  method?: "GET" | "POST" | "DELETE";
  body?: unknown;
  token?: string | null;
}

async function authRequest<T>(path: string, options: AuthRequestOptions = {}): Promise<T> {
  const { method = "GET", body, token } = options;

  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), 15_000);

  let response: Response;
  try {
    response = await fetch(`${apiBase()}${path}`, {
      method,
      headers: {
        Accept: "application/json",
        ...(body !== undefined ? { "Content-Type": "application/json" } : {}),
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
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

  if (response.status === 401 && token) {
    // Token révoqué/expiré : la session locale est purgée (fail-closed).
    writeSession(null);
  }

  if (!response.ok) {
    const message =
      (typeof payload === "object" &&
        payload !== null &&
        typeof (payload as { message?: unknown }).message === "string" &&
        ((payload as { message: string }).message as string)) ||
      defaultMessage(response.status);
    throw new ApiError(message, response.status, payload);
  }

  return payload as T;
}

function defaultMessage(status: number): string {
  if (status === 401) return "Veuillez vous connecter pour continuer.";
  if (status === 404) return "Ressource introuvable.";
  if (status === 422) return "Les informations envoyées sont invalides.";
  if (status === 423) return "Compte temporairement verrouillé. Réessayez dans 15 minutes.";
  if (status === 429) return "Trop de tentatives. Merci de patienter un instant.";
  return "Une erreur est survenue. Veuillez réessayer.";
}

/* ── Endpoints compte ───────────────────────────────────────────────────── */

interface AuthPayload {
  data: { account: AccountProfile; token: string; claimed_orders?: number };
}

export async function registerAccount(input: {
  name: string;
  email: string;
  phone?: string;
  password: string;
}): Promise<AccountSession & { claimedOrders: number }> {
  const payload = await authRequest<AuthPayload>("/public/market/account/register", {
    method: "POST",
    body: input,
  });
  const session = { token: payload.data.token, account: payload.data.account };
  writeSession(session);
  return { ...session, claimedOrders: payload.data.claimed_orders ?? 0 };
}

export async function loginAccount(input: { email: string; password: string }): Promise<AccountSession> {
  const payload = await authRequest<AuthPayload>("/public/market/account/login", {
    method: "POST",
    body: input,
  });
  const session = { token: payload.data.token, account: payload.data.account };
  writeSession(session);
  return session;
}

export async function logoutAccount(): Promise<void> {
  const token = accountToken();
  if (token) {
    try {
      await authRequest("/public/market/account/logout", { method: "POST", token });
    } catch {
      // La révocation serveur a échoué (réseau) : la session locale est
      // tout de même purgée.
    }
  }
  writeSession(null);
}

export async function fetchAccountOrders(): Promise<AccountOrder[]> {
  const payload = await authRequest<{ data: AccountOrder[] }>("/public/market/account/orders", {
    token: accountToken(),
  });
  return Array.isArray(payload.data) ? payload.data : [];
}

export async function fetchFavorites(): Promise<Favorite[]> {
  const payload = await authRequest<{ data: Favorite[] }>("/public/market/account/favorites", {
    token: accountToken(),
  });
  return Array.isArray(payload.data) ? payload.data : [];
}

export async function addFavorite(input: {
  target_type: "product" | "seller";
  product_id?: number;
  seller?: string;
}): Promise<void> {
  await authRequest("/public/market/account/favorites", {
    method: "POST",
    body: input,
    token: accountToken(),
  });
}

export async function removeFavorite(id: number): Promise<void> {
  await authRequest(`/public/market/account/favorites/${id}`, {
    method: "DELETE",
    token: accountToken(),
  });
}

export async function submitReview(input: {
  target_type: "product" | "seller";
  product_id?: number;
  seller?: string;
  rating: number;
  comment?: string;
}): Promise<void> {
  await authRequest("/public/market/account/reviews", {
    method: "POST",
    body: input,
    token: accountToken(),
  });
}

/* ── Avis publics (sans auth) ───────────────────────────────────────────── */

function normalizeReviews(payload: unknown): ReviewList {
  const record = (typeof payload === "object" && payload !== null ? payload : {}) as {
    data?: unknown;
    rating?: { average?: unknown; count?: unknown };
  };
  const data = Array.isArray(record.data) ? (record.data as PublicReview[]) : [];
  const average =
    typeof record.rating?.average === "number" ? record.rating.average : null;
  const count = typeof record.rating?.count === "number" ? record.rating.count : 0;
  return { data, rating: { average, count } };
}

export async function fetchProductReviews(productId: number | string): Promise<ReviewList> {
  return normalizeReviews(
    await authRequest<unknown>(`/public/market/products/${productId}/reviews`),
  );
}

export async function fetchSellerReviews(slug: string): Promise<ReviewList> {
  return normalizeReviews(
    await authRequest<unknown>(`/public/market/sellers/${encodeURIComponent(slug)}/reviews`),
  );
}
