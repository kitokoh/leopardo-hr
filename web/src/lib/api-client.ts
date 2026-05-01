/**
 * Client API de base pour communiquer avec le backend Laravel.
 */

import {
  AUTH_TOKEN_KEY,
  clearAuthSession,
  getApiErrorMessage,
  getPreferredLocale,
} from '@/lib/i18n';

const LOCAL_API_BASE_URL = 'http://localhost:8000/api/v1';
const DEPLOYED_API_BASE_URL = 'https://gestionemployerbackend.onrender.com/api/v1';

const API_BASE_URL =
  (process.env.NEXT_PUBLIC_API_URL ||
    (process.env.NODE_ENV === 'production' ? DEPLOYED_API_BASE_URL : LOCAL_API_BASE_URL)).replace(/\/+$/, '');

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

export async function apiFetch(endpoint: string, options: RequestInit = {}) {
  const token = typeof window !== 'undefined' ? localStorage.getItem(AUTH_TOKEN_KEY) : null;

  const headers = {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    'Accept-Language': typeof window !== 'undefined' ? getPreferredLocale() : 'fr',
    ...(token ? { 'Authorization': `Bearer ${token}` } : {}),
    ...options.headers,
  };

  const response = await fetch(`${API_BASE_URL}${endpoint}`, {
    ...options,
    headers,
  });

  if (response.status === 401 && typeof window !== 'undefined') {
    clearAuthSession();
    window.location.href = '/auth/login';
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
