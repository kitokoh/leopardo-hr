/**
 * Centralisation de l'URL de l'API backend (audit #1701).
 *
 * Avant : l'URL de production était un fallback codé en dur dans ~15
 * fichiers (route handlers, api-client, checkout, lead-capture), avec des
 * copies divergentes. Ici, une seule source de vérité :
 *   - `API_PROXY_TARGET` > `BACKEND_API_URL` > `NEXT_PUBLIC_API_URL`
 *     > défaut `https://gestionemployerbackend.onrender.com/api/v1`
 *     (API Laravel réellement en ligne, cf. docs/DEPLOYMENT_PRODUCTION.md).
 */

export const DEFAULT_BACKEND_API_URL = 'https://gestionemployerbackend.onrender.com/api/v1';

/** URL de base backend utilisée côté serveur (route handlers). */
export function resolveBackendBaseUrl(): string {
  return (
    process.env.API_PROXY_TARGET ||
    process.env.BACKEND_API_URL ||
    process.env.NEXT_PUBLIC_API_URL ||
    DEFAULT_BACKEND_API_URL
  ).replace(/\/$/, '');
}

/** URL de base backend utilisée côté client (navigateur). */
export function getApiBaseUrl(): string {
  return (
    (typeof process !== 'undefined' && process.env.NEXT_PUBLIC_API_URL) ||
    DEFAULT_BACKEND_API_URL
  ).replace(/\/$/, '');
}
