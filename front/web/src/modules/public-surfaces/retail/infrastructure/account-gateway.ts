/**
 * Adaptateur réseau — verticale Retail : le compte acheteur.
 *
 * Repris de `front/marketplace/src/lib/api.ts` (#9627).
 *
 * ## Session par cookie, pas par jeton
 *
 * Depuis #8096, la session est portée par un cookie `HttpOnly` posé par l'API.
 * Tous les appels partent donc en `credentials: 'include'` et **aucun** en-tête
 * `Authorization` n'est construit ici. Le jeton legacy n'existe que pour la
 * migration douce (`restoreBuyerSession`).
 *
 * ## Contrat de panne
 *
 * Les lectures rendent `null` quand l'API ne répond pas, jamais une liste vide :
 * « aucun favori » et « favoris indisponibles » ne doivent pas se ressembler.
 */

import { resolveBackendBaseUrl } from '@/lib/backend-url';

import type {
  AccountOrder,
  BuyerProfile,
  BuyerSession,
  ProductReview,
  ProductReviews,
} from '../domain/buyer';
import type { Product } from '../domain/product';
import { toProduct } from './market-gateway';

const ACCOUNT = 'public/market/account';

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function toNumber(value: unknown, fallback: number): number {
  return typeof value === 'number' && Number.isFinite(value) ? value : fallback;
}

/** Erreur HTTP portant le statut — l'appelant distingue 401 de 422. */
export class AccountApiError extends Error {
  readonly status: number;

  constructor(message: string, status: number) {
    super(message);
    this.name = 'AccountApiError';
    this.status = status;
  }
}

async function request<T>(path: string, init: RequestInit = {}): Promise<T> {
  const response = await fetch(`${resolveBackendBaseUrl()}/${path}`, {
    ...init,
    headers: {
      Accept: 'application/json',
      ...(init.body ? { 'Content-Type': 'application/json' } : {}),
      ...init.headers,
    },
    // La session est un cookie HttpOnly : sans `include`, l'API répond 401.
    credentials: 'include',
    cache: 'no-store',
  });

  const text = await response.text();
  let payload: unknown = null;

  if (text) {
    try {
      payload = JSON.parse(text) as unknown;
    } catch {
      payload = null;
    }
  }

  if (!response.ok) {
    const message =
      isRecord(payload) && typeof payload.message === 'string'
        ? payload.message
        : `HTTP ${response.status}`;

    throw new AccountApiError(message, response.status);
  }

  return payload as T;
}

function toBuyerProfile(raw: unknown): BuyerProfile {
  const record = isRecord(raw) ? raw : {};

  return {
    name: typeof record.name === 'string' ? record.name : '',
    email: typeof record.email === 'string' ? record.email : '',
    phone: typeof record.phone === 'string' ? record.phone : null,
    createdAt: typeof record.created_at === 'string' ? record.created_at : null,
  };
}

function toBuyerSession(payload: unknown): BuyerSession {
  const record = isRecord(payload) && isRecord(payload.data) ? payload.data : {};

  // Le jeton opaque reste présent dans la réponse (migration douce côté API)
  // mais n'est volontairement plus lu : le cookie est le seul porteur retenu.
  return { buyer: toBuyerProfile(record.buyer) };
}

/** Inscription légère : nom, e-mail, mot de passe (téléphone optionnel). */
export async function registerBuyer(payload: {
  name: string;
  email: string;
  password: string;
  phone?: string;
}): Promise<BuyerSession> {
  return toBuyerSession(
    await request<unknown>(`${ACCOUNT}/register`, {
      method: 'POST',
      body: JSON.stringify(payload),
    }),
  );
}

/** Connexion ; lève `AccountApiError` 401 sur identifiants invalides. */
export async function loginBuyer(payload: {
  email: string;
  password: string;
}): Promise<BuyerSession> {
  return toBuyerSession(
    await request<unknown>(`${ACCOUNT}/login`, {
      method: 'POST',
      body: JSON.stringify(payload),
    }),
  );
}

/** Déconnexion — l'API invalide le cookie. */
export async function logoutBuyer(): Promise<void> {
  await request<unknown>(`${ACCOUNT}/logout`, { method: 'POST' });
}

/** Profil courant ; lève 401 si la session a expiré. */
export async function fetchBuyerProfile(): Promise<BuyerProfile> {
  const payload = await request<unknown>(`${ACCOUNT}/me`);

  return toBuyerProfile(isRecord(payload) && isRecord(payload.data) ? payload.data : payload);
}

/**
 * Échange un jeton legacy contre le cookie de session.
 *
 * À appeler **une fois** à la détection d'une ancienne session, juste avant de
 * purger le jeton du stockage.
 */
export async function restoreBuyerSession(legacyToken: string): Promise<BuyerProfile> {
  const payload = await request<unknown>(`${ACCOUNT}/session/restore`, {
    method: 'POST',
    headers: { Authorization: `Bearer ${legacyToken}` },
  });

  const record = isRecord(payload) && isRecord(payload.data) ? payload.data : {};

  return toBuyerProfile(record.buyer);
}

/** Historique des commandes du compte. */
export async function fetchBuyerOrders(page = 1): Promise<AccountOrder[] | null> {
  try {
    const payload = await request<unknown>(`${ACCOUNT}/orders?page=${page}`);
    const record = isRecord(payload) ? payload : {};
    const data = Array.isArray(record.data) ? record.data : [];

    return data.flatMap((entry) => {
      if (!isRecord(entry)) {
        return [];
      }

      const seller = isRecord(entry.seller) ? entry.seller : {};

      return [
        {
          reference: typeof entry.reference === 'string' ? entry.reference : '',
          sellerName:
            (typeof seller.name === 'string' && seller.name) ||
            (typeof seller.shop_name === 'string' && seller.shop_name) ||
            '',
          totalMinor: toNumber(entry.total_minor, 0),
          currency: typeof entry.currency === 'string' ? entry.currency : 'EUR',
          status: typeof entry.fulfillment_status === 'string' ? entry.fulfillment_status : 'pending',
          createdAt: typeof entry.created_at === 'string' ? entry.created_at : null,
          trackingToken: typeof entry.tracking_token === 'string' ? entry.tracking_token : '',
        },
      ];
    });
  } catch {
    return null;
  }
}

/** Produits favoris ; `null` si l'API ne répond pas. */
export async function fetchFavorites(): Promise<Product[] | null> {
  try {
    const payload = await request<unknown>(`${ACCOUNT}/favorites`);
    const data = isRecord(payload) && Array.isArray(payload.data) ? payload.data : [];

    return data.map((entry) => toProduct(entry as Parameters<typeof toProduct>[0]));
  } catch {
    return null;
  }
}

/** Ajoute un produit aux favoris. */
export async function addFavorite(productId: number): Promise<void> {
  await request<unknown>(`${ACCOUNT}/favorites`, {
    method: 'POST',
    body: JSON.stringify({ product_id: productId }),
  });
}

/** Retire un produit des favoris. */
export async function removeFavorite(productId: number): Promise<void> {
  await request<unknown>(`${ACCOUNT}/favorites/${productId}`, { method: 'DELETE' });
}

/**
 * Dépose un avis vérifié.
 *
 * L'API refuse un avis sans commande livrée : le domaine expose `canReview`
 * pour que l'UI n'affiche pas un formulaire qui serait rejeté.
 */
export async function submitReview(payload: {
  orderReference: string;
  productId: number;
  rating: number;
  comment?: string;
}): Promise<void> {
  await request<unknown>(`${ACCOUNT}/reviews`, {
    method: 'POST',
    body: JSON.stringify({
      order_reference: payload.orderReference,
      product_id: payload.productId,
      rating: payload.rating,
      ...(payload.comment !== undefined && payload.comment.trim() !== ''
        ? { comment: payload.comment.trim() }
        : {}),
    }),
  });
}

/** Avis publics d'un produit ; `null` si l'API ne répond pas. */
export async function fetchProductReviews(
  productId: number | string,
  page = 1,
): Promise<ProductReviews | null> {
  try {
    const payload = await request<unknown>(
      `public/market/products/${encodeURIComponent(String(productId))}/reviews?page=${page}`,
    );
    const record = isRecord(payload) ? payload : {};
    const meta = isRecord(record.meta) ? record.meta : {};
    const data = Array.isArray(record.data) ? record.data : [];

    const reviews: ProductReview[] = data.flatMap((entry) => {
      if (!isRecord(entry)) {
        return [];
      }

      return [
        {
          rating: toNumber(entry.rating, 0),
          comment: typeof entry.comment === 'string' ? entry.comment : null,
          buyerName: typeof entry.buyer_name === 'string' ? entry.buyer_name : '',
          createdAt: typeof entry.created_at === 'string' ? entry.created_at : null,
        },
      ];
    });

    return {
      reviews,
      ratingAvg: typeof meta.rating_avg === 'number' ? meta.rating_avg : null,
      ratingCount: toNumber(meta.rating_count, 0),
    };
  } catch {
    return null;
  }
}
