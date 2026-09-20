/**
 * URL de base de l'API backend — même chaîne de résolution que front/web
 * (`src/lib/backend-url.ts`, audit #1701) :
 *   `API_PROXY_TARGET` > `BACKEND_API_URL` > `NEXT_PUBLIC_API_URL`
 *   > défaut `https://gestionemployerbackend.onrender.com/api/v1`
 * (API Laravel dev réellement en ligne — cf. docs/ops/DOMAINS.md).
 *
 * Server-side UNIQUEMENT (route handler proxy + pages SSR). Le navigateur,
 * lui, ne parle qu'au proxy same-origin `/api/v1/*`.
 */

export const DEFAULT_BACKEND_API_URL =
  "https://gestionemployerbackend.onrender.com/api/v1";

export function resolveBackendBaseUrl(): string {
  return (
    process.env.API_PROXY_TARGET ||
    process.env.BACKEND_API_URL ||
    process.env.NEXT_PUBLIC_API_URL ||
    DEFAULT_BACKEND_API_URL
  ).replace(/\/$/, "");
}
