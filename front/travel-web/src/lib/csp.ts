/**
 * Content-Security-Policy à nonce strict — source UNIQUE des directives
 * (issue #8022, tranche 1 — pattern #7650 de front/web, dont ce module est
 * l'adaptation sans les origines analytics tierces).
 *
 * Historique : la CSP vivait en statique dans `next.config.ts` (#7980) avec
 * `script-src 'self' 'unsafe-inline'` — une XSS inline s'exécutait donc
 * toujours. Un nonce est par définition PAR REQUÊTE, or les headers de
 * `next.config.ts` sont statiques : la politique est désormais construite
 * ICI et émise par le proxy (`src/proxy.ts`) en mode enforce, avec un nonce
 * régénéré à chaque requête. Ne PAS réintroduire de CSP dans
 * `next.config.ts` ou `vercel.json` : deux politiques enforce
 * s'intersectent et la copie statique (sans nonce) bloquerait tout script.
 *
 * Pourquoi ça tient : le root layout lit `cookies()` (#7841, locale) —
 * TOUTES les routes HTML sont donc rendues dynamiquement, et Next lit le
 * nonce dans l'en-tête `Content-Security-Policy` de la REQUÊTE (cf.
 * `parseRequestHeaders` → `getScriptNonceFromHeader`) pour l'apposer
 * lui-même sur ses scripts inline (bootstrap, flight data) et ses preloads.
 *
 * script-src, lecture par génération de navigateur :
 *  - CSP3 (tout navigateur actuel) : `'nonce-…' + 'strict-dynamic'` — la
 *    liste d'hôtes est ignorée, seuls les scripts nonce-és et leur
 *    descendance s'exécutent. **Plus aucun `'unsafe-inline'`** : c'est
 *    précisément ce qui rend la CSP opposable au HTML injecté.
 *  - CSP2 sans strict-dynamic (vieux Safari) : le nonce reste honoré pour
 *    l'inline SSR, `'self'` sert de repli pour les scripts externes.
 *
 * style-src conserve `'unsafe-inline'` : Tailwind injecte des styles inline
 * — hors périmètre de #8022 (le vecteur XSS exécutable est script-src).
 */

import { PHASE_PRODUCTION_BUILD } from "next/constants";

/**
 * Repli dev/test UNIQUEMENT (#8022, tranche 3 — même constante que
 * `backend-url.ts`, #7963) : origine du backend Laravel local
 * (`php artisan serve`, port 8000). Plus aucune origine distante en dur.
 */
const DEFAULT_API_ORIGIN = "http://localhost:8000";

function isNextBuildPhase(): boolean {
  return process.env.NEXT_PHASE === PHASE_PRODUCTION_BUILD;
}

function isProductionRuntime(): boolean {
  return (
    (process.env.NODE_ENV === "production" ||
      process.env.VERCEL_ENV === "production") &&
    !isNextBuildPhase()
  );
}

let warnedCspFallback = false;

/**
 * Mêmes règles fail-fast que `backend-url.ts` (#7842/#7963) : variable
 * absente ou invalide → erreur explicite pendant `next build` ET au runtime
 * de production ; repli signalé par un `console.warn` en dev/test seulement
 * (tranche 3 de #8022 : ce repli couvre désormais `http://localhost:8000`,
 * le backend Laravel local — auparavant le connect-src tombait à `'self'`
 * seul et les appels API directs du navigateur étaient bloqués en dev).
 */
function devFallbackApiOrigin(reason: string): string {
  if (isNextBuildPhase()) {
    throw new Error(
      `[csp] ${reason} pendant \`next build\`. Définissez NEXT_PUBLIC_API_URL ` +
        "(ex. http://localhost:8000/api/v1 en CI, l'URL réelle de l'API en " +
        "déploiement) — le build échoue explicitement plutôt que de figer " +
        "un connect-src de repli (#7963).",
    );
  }
  if (isProductionRuntime()) {
    throw new Error(
      `[csp] ${reason} en production. Définissez NEXT_PUBLIC_API_URL ` +
        "(ex. https://api.exemple.com/api/v1) dans l'environnement de " +
        "déploiement. Aucune origine distante n'est codée en dur (#7963).",
    );
  }
  if (!warnedCspFallback) {
    warnedCspFallback = true;
    console.warn(
      `[csp] ${reason} — connect-src replié en dev/test sur ` +
        `${DEFAULT_API_ORIGIN} (backend local ; interdit au build et au ` +
        "runtime de production, #7842/#7963, tranche 3 de #8022).",
    );
  }
  return DEFAULT_API_ORIGIN;
}

export function resolveApiOrigin(): string {
  const configured = process.env.NEXT_PUBLIC_API_URL;
  if (!configured) {
    return devFallbackApiOrigin("NEXT_PUBLIC_API_URL absente");
  }
  try {
    return new URL(configured).origin;
  } catch {
    return devFallbackApiOrigin(
      `NEXT_PUBLIC_API_URL invalide (« ${configured} »)`,
    );
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
    apiOrigin,
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
 * Levier de ROLLBACK opérationnel (même convention que #7650) : la CSP est
 * ENFORCE par défaut. Poser `CSP_REPORT_ONLY=true` (env Vercel) rebascule en
 * Report-Only sans redéploiement de code — à n'utiliser qu'en cas
 * d'incident, et à retirer après correction.
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
