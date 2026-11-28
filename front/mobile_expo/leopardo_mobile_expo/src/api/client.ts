import axios, { AxiosError, AxiosInstance } from 'axios';
import { secureStorage } from './secureStorage';

/**
 * URL de base de l'API Leopardo.
 *
 * Definie par EXPO_PUBLIC_API_URL (fichier .env a la racine du projet).
 * Defaut : emulateur Android -> machine hote sur le port 8000.
 */
export const API_BASE_URL =
  process.env.EXPO_PUBLIC_API_URL ?? 'http://10.0.2.2:8000/api/v1';

/** Callback declenche quand la session est revoquee (401). */
type UnauthorizedHandler = () => void;

let onUnauthorized: UnauthorizedHandler | null = null;

export function setUnauthorizedHandler(handler: UnauthorizedHandler | null) {
  onUnauthorized = handler;
}

export const api: AxiosInstance = axios.create({
  baseURL: API_BASE_URL,
  timeout: 20000,
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
});

// --- Requete : injection du jeton Bearer + langue -------------------------
api.interceptors.request.use(async (config) => {
  const token = await secureStorage.getToken();
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  config.headers['Accept-Language'] = 'fr';
  return config;
});

// --- Reponse : gestion centralisee du 401 --------------------------------
api.interceptors.response.use(
  (response) => response,
  async (error: AxiosError) => {
    if (error.response?.status === 401) {
      await secureStorage.clearAll();
      onUnauthorized?.();
    }
    return Promise.reject(error);
  },
);

/**
 * Extrait le message d'erreur lisible d'une reponse API Leopardo.
 *
 * L'API renvoie soit { message }, soit { errors: { champ: [msg] } }
 * (validation Laravel), soit une chaine brute.
 */
export function extractErrorMessage(error: unknown): string {
  if (axios.isAxiosError(error)) {
    const data = error.response?.data as
      | { message?: string; errors?: Record<string, string[]> }
      | undefined;

    if (data?.errors) {
      const first = Object.values(data.errors)[0];
      if (Array.isArray(first) && first.length > 0) return first[0];
    }
    if (data?.message) return data.message;

    if (error.code === 'ECONNABORTED') {
      return 'Le serveur met trop de temps a repondre. Verifiez votre connexion.';
    }
    if (!error.response) {
      return `Impossible de joindre le serveur (${API_BASE_URL}).`;
    }
    return `Erreur ${error.response.status}.`;
  }
  if (error instanceof Error) return error.message;
  return 'Une erreur inattendue est survenue.';
}

/**
 * L'API enveloppe la plupart des reponses dans { data: ... }.
 * Ce helper accepte les deux formes (enveloppee ou racine).
 */
export function unwrap<T>(payload: unknown): T {
  if (payload && typeof payload === 'object' && 'data' in payload) {
    return (payload as { data: T }).data;
  }
  return payload as T;
}

/**
 * Normalise une liste paginee Laravel ({ data: [...] }) ou un tableau brut.
 */
export function unwrapList<T>(payload: unknown): T[] {
  const body = unwrap<unknown>(payload);
  if (Array.isArray(body)) return body as T[];
  if (body && typeof body === 'object' && 'data' in body) {
    const inner = (body as { data: unknown }).data;
    if (Array.isArray(inner)) return inner as T[];
  }
  return [];
}
