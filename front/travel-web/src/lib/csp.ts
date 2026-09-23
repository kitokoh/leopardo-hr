/**
 * Content-Security-Policy à nonce par requête — tranche #8022 (suivi #8006).
 *
 * Avant cette tranche, la CSP vivait en header STATIQUE dans
 * `next.config.ts` (#7980) avec `script-src 'unsafe-inline'` : une XSS
 * inline s'exécutait toujours. Même bascule que front/web (#7650) :
 * la CSP est construite ICI, émise par `src/middleware.ts` avec un nonce
 * par requête + `'strict-dynamic'` — plus aucun `'unsafe-inline'` en
 * script-src. Le root layout lit `cookies()` (i18n), donc TOUTES les
 * routes HTML sont rendues dynamiquement : Next lit le nonce dans
 * l'en-tête `Content-Security-Policy` de la REQUÊTE et l'appose sur ses
 * scripts inline (bootstrap, flight data).
 *
 * style-src conserve `'unsafe-inline'` (Tailwind) — le vecteur XSS
 * exécutable est script-src, comme acté par #7650.
 */

/**
 * Repli dev/test UNIQUEMENT (#8022 point 3, aligné #7963) : origine du
 * backend Laravel local (`php artisan serve`, port 8000) quand
 * `NEXT_PUBLIC_API_URL` est absente — sans cela, les appels API dev
 * étaient bloqués par un connect-src réduit à 'self'.
 */
const DEFAULT_DEV_API_ORIGIN = "http://localhost:8000";

export function resolveApiOrigin(): string {
  const raw = process.env.NEXT_PUBLIC_API_URL;
  const isDev = process.env.NODE_ENV === "development";
  if (!raw) {
    return isDev ? DEFAULT_DEV_API_ORIGIN : "";
  }
  try {
    return new URL(raw).origin;
  } catch {
    return isDev ? DEFAULT_DEV_API_ORIGIN : "";
  }
}

export function buildCspDirectives({
  nonce,
  isDev = process.env.NODE_ENV === "development",
}: {
  nonce: string;
  isDev?: boolean;
}): string {
  const apiOrigin = resolveApiOrigin();

  const scriptSrc = [
    "'self'",
    `'nonce-${nonce}'`,
    "'strict-dynamic'",
    // `next dev` (React Refresh/Turbopack) évalue du code : dev uniquement.
    ...(isDev ? ["'unsafe-eval'"] : []),
  ].join(" ");

  const connectSrc = [
    "'self'",
    ...(apiOrigin ? [apiOrigin] : []),
    // HMR de `next dev` (websocket local) : dev uniquement.
    ...(isDev ? ["ws:", "wss:"] : []),
  ].join(" ");

  return [
    "default-src 'self'",
    `script-src ${scriptSrc}`,
    "style-src 'self' 'unsafe-inline'",
    "img-src 'self' data: https:",
    "font-src 'self' data:",
    `connect-src ${connectSrc}`,
    "object-src 'none'",
    "base-uri 'self'",
    "form-action 'self'",
    "frame-ancestors 'none'",
  ].join("; ");
}

/**
 * Nonce base64 par requête (128 bits d'entropie Web Crypto). `btoa` plutôt
 * que Buffer : le middleware Next tourne sur le runtime edge.
 */
export function generateCspNonce(): string {
  const bytes = new Uint8Array(16);
  crypto.getRandomValues(bytes);
  return btoa(String.fromCharCode(...bytes));
}
