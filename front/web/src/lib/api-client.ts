/**
 * Client API de base pour communiquer avec le backend Laravel.
 *
 * Handles Render cold-start gracefully with progressive retry
 * and extended timeouts for login requests.
 */

import {
  AUTH_TOKEN_KEY,
  clearAuthSession,
  getApiErrorMessage,
  getPreferredLocale,
} from '@/lib/i18n';

const LOCAL_API_BASE_URL = 'http://localhost:8000/api/v1';
const DEPLOYED_API_BASE_URL = 'https://gestionemployerbackend.onrender.com/api/v1';

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
  const token = typeof window !== 'undefined' ? localStorage.getItem(AUTH_TOKEN_KEY) : null;
  const isLoginRequest = endpoint === '/auth/login' || endpoint === '/platform/auth/login';
  const timeoutMs = isLoginRequest ? 60000 : 20000;

  const headers = {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    'Accept-Language': typeof window !== 'undefined' ? getPreferredLocale() : 'fr',
    ...(token ? { 'Authorization': `Bearer ${token}` } : {}),
    ...options.headers,
  };

  const response = await fetchWithRetry(
    `${API_BASE_URL}${endpoint}`,
    { ...options, headers },
    timeoutMs,
    {
      maxRetries: isLoginRequest ? 3 : (retryOptions?.maxRetries ?? 2),
      onRetry: retryOptions?.onRetry,
    },
  );

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
