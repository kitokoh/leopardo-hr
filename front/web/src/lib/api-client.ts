/**
 * Client API de base pour communiquer avec le backend Laravel.
 *
 * Handles Render cold-start gracefully with progressive retry
 * and extended timeouts for login requests.
 */

import {
  clearAuthSession,
  getApiErrorMessage,
  getPreferredLocale,
} from '@/lib/i18n';

import { DEFAULT_BACKEND_API_URL } from '@/lib/backend-url';


declare global {
  interface RequestInit {
    /** RTMX (#5446) : forcer une réponse fraîche (ignore l'ETag caché). */
    _cacheBust?: boolean;
    /** RTMX (#5446) : désactiver la clé d'idempotence pour cette mutation. */
    _idempotent?: boolean;
  }
}

const LOCAL_API_BASE_URL = 'http://localhost:8000/api/v1';
const DEPLOYED_API_BASE_URL = DEFAULT_BACKEND_API_URL;

/**
 * In the browser, production requests go through the Next same-origin proxy
 * (/api/v1/...) by default. This avoids CORS drift between Vercel and Render
 * while keeping the Laravel API as the single backend.
 */
const VERCEL_PROXY_BASE_URL = '/api/v1';

function resolveApiBaseUrl(): string {
  if (typeof window !== 'undefined' && process.env.NODE_ENV === 'production') {
    if (process.env.NEXT_PUBLIC_API_DIRECT === 'true' && process.env.NEXT_PUBLIC_API_URL) {
      return process.env.NEXT_PUBLIC_API_URL;
    }

    return VERCEL_PROXY_BASE_URL;
  }

  if (process.env.NEXT_PUBLIC_API_URL) {
    return process.env.NEXT_PUBLIC_API_URL;
  }

  if (process.env.NODE_ENV === 'production') {
    return DEPLOYED_API_BASE_URL;
  }
  return LOCAL_API_BASE_URL;
}

const API_BASE_URL = resolveApiBaseUrl();


// ── RTMX client (#5446) — GET conditionnels (ETag/304) + Idempotency-Key ──
// Le socle serveur (#5277) expose ETag (sha1 du corps) + rejeu idempotent
// 24 h des écritures. Ici : GET avec If-None-Match (304 = succès, corps
// caché servi depuis sessionStorage) et mutations avec une clé d'idempotence
// stable par action logique (le serveur rejoue la 1re 2xx au lieu de
// dupliquer — sécurise aussi les retries 502/503/504 existants).

const RTMX_ETAG_KEY = 'rtmx_etag_v1';
const RTMX_IDEM_KEY = 'rtmx_idem_v1';

type RtmxCacheEntry = { etag: string; body: unknown; ts: number };

function rtmxSessionGet<T>(key: string): T | null {
  if (typeof window === 'undefined') return null;
  try {
    return JSON.parse(window.sessionStorage.getItem(key) || 'null') as T | null;
  } catch {
    return null;
  }
}

function rtmxSessionSet(key: string, value: unknown): void {
  if (typeof window === 'undefined') return;
  try {
    window.sessionStorage.setItem(key, JSON.stringify(value));
  } catch {
    // sessionStorage indisponible — cache mémoire uniquement.
  }
}

function rtmxCacheKey(url: string): string {
  try {
    const u = new URL(url, 'http://local');
    return u.pathname + u.search;
  } catch {
    return url;
  }
}

function rtmxUuid(): string {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID();
  }
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
    const r = (Math.random() * 16) | 0;
    const v = c === 'x' ? r : (r & 0x3) | 0x8;
    return v.toString(16);
  });
}

function rtmxIdempotencyKey(method: string, url: string, body?: unknown): string {
  const store = rtmxSessionGet<Record<string, string>>(RTMX_IDEM_KEY) || {};
  const logical = `${method}:${url}:${typeof body === 'string' ? body : JSON.stringify(body ?? {})}`;
  // FNV-1a 32 bits — déduplication des actions identiques (double-clic/retry).
  let h = 0x811c9dc5;
  for (let i = 0; i < logical.length; i++) {
    h ^= logical.charCodeAt(i);
    h = Math.imul(h, 0x01000193);
  }
  const storeKey = `v1:${h >>> 0}`;
  if (!store[storeKey]) {
    store[storeKey] = rtmxUuid();
  }
  rtmxSessionSet(RTMX_IDEM_KEY, store);
  return store[storeKey];
}

export class ApiError extends Error {
  status: number;
  code?: string;

  constructor(message: string, status: number, code?: string) {
    super(message);
    this.name = 'ApiError';
    this.status = status;
    this.code = code;
  }
}

type RetryOptions = {
  maxRetries?: number;
  onRetry?: (attempt: number, error: unknown) => void;
};

async function fetchWithRetry(
  url: string,
  options: RequestInit,
  timeoutMs: number,
  { maxRetries = 2, onRetry }: RetryOptions = {},
): Promise<Response> {
  let lastError: unknown;

  for (let attempt = 0; attempt <= maxRetries; attempt++) {
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), timeoutMs);

    try {
      const response = await fetch(url, {
        ...options,
        signal: options.signal ?? controller.signal,
      });

      clearTimeout(timeout);

      if (response.status === 502 || response.status === 503 || response.status === 504) {
        if (attempt < maxRetries) {
          onRetry?.(attempt + 1, new ApiError(`Server returned ${response.status}`, response.status, 'COLD_START'));
          const backoff = Math.min(3000 * (attempt + 1), 10000);
          await new Promise(r => setTimeout(r, backoff));
          continue;
        }
      }

      return response;
    } catch (error) {
      clearTimeout(timeout);
      lastError = error;

      const isAbort = error instanceof DOMException && error.name === 'AbortError';
      const isNetwork = error instanceof TypeError && error.message.includes('fetch');

      if ((isAbort || isNetwork) && attempt < maxRetries) {
        onRetry?.(attempt + 1, error);
        const backoff = Math.min(3000 * (attempt + 1), 10000);
        await new Promise(r => setTimeout(r, backoff));
        continue;
      }

      if (isAbort) {
        throw new ApiError(
          'Le serveur met trop de temps a repondre. Reessayez dans quelques instants.',
          408,
          'TIMEOUT',
        );
      }

      throw error;
    }
  }

  throw lastError;
}

export async function apiFetch(
  endpoint: string,
  options: RequestInit = {},
  retryOptions?: RetryOptions,
) {
  // Security fix (#1299): The auth token is now stored in a httpOnly cookie
  // (`leopardo_token`) set by the Next.js /api/v1/auth/login proxy route.
  // The browser sends it automatically on every same-origin request — no JS
  // code needs to read or attach it. We no longer read from localStorage here.
  // The proxy route (src/app/api/v1/[...path]/route.ts) reads the httpOnly
  // cookie server-side and injects it as a Bearer Authorization header before
  // forwarding the request to Laravel.
  //
  const isLoginRequest = endpoint === '/auth/login' || endpoint === '/platform/auth/login';
  const timeoutMs = isLoginRequest ? 60000 : 20000;

  const method = (options.method || 'GET').toUpperCase();

  const headers = new Headers({
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    'Accept-Language': typeof window !== 'undefined' ? getPreferredLocale() : 'fr',
  });
  for (const [k, v] of Object.entries(options.headers ?? {})) {
    headers.set(k, String(v));
  }

  // RTMX (#5446) : GET conditionnel + clé d'idempotence par action logique.
  if (method === 'GET' && options._cacheBust !== true) {
    const cached = rtmxSessionGet<Record<string, RtmxCacheEntry>>(RTMX_ETAG_KEY)?.[rtmxCacheKey(endpoint)];
    if (cached?.etag) {
      headers.set('If-None-Match', cached.etag);
    }
  } else if (['POST', 'PUT', 'PATCH'].includes(method) && options._idempotent !== false) {
    headers.set('Idempotency-Key', rtmxIdempotencyKey(method, endpoint, options.body));
  }

  const response = await fetchWithRetry(
    `${API_BASE_URL}${endpoint}`,
    { ...options, headers },
    timeoutMs,
    {
      maxRetries: isLoginRequest ? 3 : (retryOptions?.maxRetries ?? 2),
      onRetry: retryOptions?.onRetry,
    },
  );

  // RTMX (#5446) : 304 Not Modified = succès — servir le corps caché.
  if (response.status === 304) {
    const cached = rtmxSessionGet<Record<string, RtmxCacheEntry>>(RTMX_ETAG_KEY)?.[rtmxCacheKey(endpoint)];
    if (cached) {
      return new Response(JSON.stringify(cached.body), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      });
    }
    // Cache absent (sessionStorage purgé) : rejouer sans If-None-Match.
    return apiFetch(endpoint, { ...options, _cacheBust: true }, retryOptions);
  }

  // RTMX (#5446) : mémoriser l'ETag des GET 2xx JSON pour les prochaines
  // lectures (les téléchargements — PDF/CSV — ne sont pas mis en cache).
  if (method === 'GET' && response.ok) {
    const etag = response.headers.get('etag');
    const contentType = response.headers.get('content-type') || '';
    if (etag && contentType.includes('application/json') && options._cacheBust !== true) {
      const store = rtmxSessionGet<Record<string, RtmxCacheEntry>>(RTMX_ETAG_KEY) || {};
      const body = await response.clone().json();
      store[rtmxCacheKey(endpoint)] = { etag, body, ts: Date.now() };
      rtmxSessionSet(RTMX_ETAG_KEY, store);
    }
  }

  if (response.status === 401 && typeof window !== 'undefined' && !isLoginRequest) {
    clearAuthSession();
    window.location.replace('/auth/login');
    throw new Error('Unauthorized: redirecting to login');
  }

  if (!response.ok) {
    let payload: unknown = null;

    try {
      payload = await response.json();
    } catch {
      payload = null;
    }

    const message = getApiErrorMessage(payload, 'Une erreur est survenue.');
    const code = payload && typeof payload === 'object' && 'error' in payload
      ? String((payload as Record<string, unknown>).error)
      : undefined;

    throw new ApiError(message, response.status, code);
  }

  return response;
}
