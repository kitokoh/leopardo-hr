/**
 * Content-Security-Policy — source UNIQUE des directives (#8022, suivi de
 * #7980 ; pattern #7650 de front/web).
 *
 * Avant #8022, la CSP vivait dans `next.config.ts` — statique, donc avec
 * `script-src 'unsafe-inline'` : une XSS inline s'exécutait encore. Elle est
 * désormais construite ICI et émise par le proxy (`src/proxy.ts`) avec un
 * nonce PAR REQUÊTE : Next lit le nonce dans l'en-tête
 * `Content-Security-Policy` de la requête et l'appose lui-même sur ses
 * scripts inline (bootstrap, flight data) et ses preloads — possible parce
 * que le root layout lit `headers()` (#8022), ce qui rend toutes les routes
 * HTML dynamiques. Aucun script inline maison (vérifié : pas de
 * `dangerouslySetInnerHTML`) — `'unsafe-inline'` disparaît de `script-src`.
 *
 * Spécificité marketplace : le navigateur appelle DIRECTEMENT l'API backend
 * pour le catalogue public (`NEXT_PUBLIC_MARKET_API_BASE`) — l'origine est
 * donc autorisée en connect-src. Les appels compte acheteur passent eux par
 * le proxy same-origin `/api/v1/*` (#8022) : 'self' suffit pour eux.
 */

import { PHASE_PRODUCTION_BUILD } from "next/constants";

// Repli dev/test UNIQUEMENT (#7963) : origine du backend Laravel local
// (`php artisan serve`, port 8000). Jamais d'origine distante en dur.
const LOCAL_API_ORIGIN = "http://localhost:8000";

function isNextBuildPhase(): boolean {
  return process.env.NEXT_PHASE === PHASE_PRODUCTION_BUILD;
}

let warnedCspFallback = false;

/**
 * Origine API autorisée en connect-src : `NEXT_PUBLIC_MARKET_API_BASE` si
 * posée ; sinon, en dev/test UNIQUEMENT, le backend local
 * `http://localhost:8000` (un dev sans variable doit pouvoir joindre
 * `php artisan serve`). En production ou au build : pas d'origine de repli
 * (le fail-fast métier reste porté par `lib/api.ts`/`lib/backend-url.ts`).
 */
export function resolveApiOrigin(): string {
  const configured = process.env.NEXT_PUBLIC_MARKET_API_BASE;
  if (configured) {
    try {
      return new URL(configured).origin;
    } catch {
      // Tombe dans le repli dev ci-dessous.
    }
  }
  if (isNextBuildPhase() || process.env.NODE_ENV === "production") {
    return "";
  }
  if (!warnedCspFallback) {
    warnedCspFallback = true;
    console.warn(
      `[csp] NEXT_PUBLIC_MARKET_API_BASE absente — connect-src replié en ` +
        `dev/test sur ${LOCAL_API_ORIGIN} (backend local, #7963).`,
    );
  }
  return LOCAL_API_ORIGIN;
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
    "upgrade-insecure-requests",
  ].join("; ");
}

/**
 * Levier de ROLLBACK opérationnel (même contrat que #7650) : la CSP est
 * ENFORCE par défaut. Poser `CSP_REPORT_ONLY=true` rebascule en Report-Only
 * sans redéploiement de code.
 */
export function cspHeaderName(): string {
  return process.env.CSP_REPORT_ONLY === "true"
    ? "Content-Security-Policy-Report-Only"
    : "Content-Security-Policy";
}

/** Nonce base64 par requête (128 bits d'entropie Web Crypto). */
export function generateCspNonce(): string {
  const bytes = new Uint8Array(16);
  crypto.getRandomValues(bytes);
  return Buffer.from(bytes).toString("base64");
}
