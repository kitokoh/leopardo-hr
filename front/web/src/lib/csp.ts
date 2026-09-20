/**
 * Content-Security-Policy — source UNIQUE des directives (issue #7650).
 *
 * Historique : la CSP est née Report-Only (#1300), la décision datée du
 * 2026-08-09 (#1607) la maintenait en Report-Only faute de câblage
 * nonce/hash — et elle vivait en TROIS copies divergentes : `next.config.ts`
 * (connect-src paramétré), `vercel.json` (connect-src FIGÉ sur l'API dev
 * Render historique) et nulle part en enforce. L'audit
 * #7650 acte la bascule : **la CSP est désormais construite ICI, émise par le
 * proxy (`src/proxy.ts`) en mode enforce, avec un nonce par requête** —
 * `next.config.ts` et `vercel.json` n'émettent plus de CSP.
 *
 * Pourquoi le proxy et pas `next.config.ts` : un nonce est par définition
 * par-requête, or les headers de `next.config.ts` sont statiques. Le root
 * layout lisant `headers()` (#3807), TOUTES les routes HTML sont rendues
 * dynamiquement : Next lit le nonce dans l'en-tête `Content-Security-Policy`
 * de la REQUÊTE (cf. `parseRequestHeaders` → `getScriptNonceFromHeader`) et
 * l'appose lui-même sur ses scripts inline (bootstrap, flight data) et ses
 * preloads. Les scripts injectés côté client (`next/script`
 * afterInteractive, GA/Mixpanel de `ConsentScripts`) sont couverts par
 * `'strict-dynamic'` (éléments non insérés par le parseur).
 *
 * script-src, lecture par génération de navigateur :
 *  - CSP3 (tout navigateur actuel) : `'nonce-…' + 'strict-dynamic'` — la
 *    liste d'hôtes est ignorée, seuls les scripts nonce-és et leur
 *    descendance s'exécutent. **Plus aucun `'unsafe-inline'`** : c'est
 *    précisément ce qui rend la CSP opposable au HTML injecté
 *    (`dangerouslySetInnerHTML` du SVG 2FA, etc.).
 *  - CSP2 sans strict-dynamic (vieux Safari) : le nonce reste honoré pour
 *    l'inline SSR, la liste d'hôtes (`'self'` + CDN analytics) sert de repli
 *    pour les scripts externes.
 *
 * style-src conserve `'unsafe-inline'` : Tailwind v4 et framer-motion
 * injectent des styles inline — hors périmètre de #7650 (le vecteur XSS
 * exécutable est script-src).
 */

import { PHASE_PRODUCTION_BUILD } from "next/constants";

// Repli dev/test UNIQUEMENT (#7963) : origine du backend Laravel local
// (`php artisan serve`, port 8000). Plus aucune origine distante en dur.
const DEFAULT_API_ORIGIN = "http://localhost:8000";

/**
 * connect-src par ENVIRONNEMENT (#7650, durci par #7842) : l'origine API
 * vient de `NEXT_PUBLIC_API_URL` (posée par environnement Vercel/Render),
 * plus aucun hardcode du service dev dans un fichier statique.
 *
 * Fail-fast (#7842, étendu au build par #7963) : en PRODUCTION
 * (`NODE_ENV === 'production'` ou `VERCEL_ENV === 'production'`), variable
 * absente ou invalide → erreur explicite. Le repli n'existe plus qu'en
 * dev/test (poste local sans `.env`) : origine du backend LOCAL
 * `http://localhost:8000`, signalée par un `console.warn` — plus aucune
 * origine distante codée en dur (#7963).
 *
 * Build Next (#7963) : pendant `next build` (`NEXT_PHASE ===
 * PHASE_PRODUCTION_BUILD`), variable absente ou invalide → erreur explicite
 * AUSSI : un build sans URL API configurée doit ÉCHOUER plutôt que de figer
 * un connect-src de repli. (Historique : #7842 avait introduit l'exception
 * inverse — jamais de throw au build — pour la CI lighthouse ; #7963 la
 * renverse, les workflows CI définissent désormais NEXT_PUBLIC_API_URL
 * explicitement.) Le fail-fast RUNTIME reste appliqué par le proxy, qui
 * émet la CSP par requête (`NEXT_PHASE` n'est alors plus posé). Même
 * détection que `backend-url.ts`/`site-url.ts`.
 */
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
        "déploiement. Le repli silencieux du connect-src vers l'API dev " +
        "onrender.com a été retiré (#7842) et plus aucune origine distante " +
        "n'est codée en dur (#7963).",
    );
  }
  if (!warnedCspFallback) {
    warnedCspFallback = true;
    console.warn(
      `[csp] ${reason} — connect-src replié en dev/test sur ` +
        `${DEFAULT_API_ORIGIN} (backend local ; interdit au build et au ` +
        "runtime de production, #7842/#7963).",
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
    // Repli CSP2 (navigateurs sans strict-dynamic) — ignoré en CSP3.
    "https://www.googletagmanager.com",
    "https://cdn4.mxpnl.com",
    "https://cdn.mxpnl.com",
    "https://browser.sentry-cdn.com",
    // `next dev` (React Refresh/Turbopack) évalue du code : dev uniquement.
    ...(isDev ? ["'unsafe-eval'"] : []),
  ].join(" ");

  const connectSrc = [
    "'self'",
    apiOrigin,
    "https://www.google-analytics.com",
    "https://www.googletagmanager.com",
    "https://api.mixpanel.com",
    "https://*.sentry.io",
    "https://*.ingest.sentry.io",
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
    "frame-src 'self' https://js.stripe.com https://checkout.stripe.com",
    "worker-src 'self' blob:",
    "object-src 'none'",
    "base-uri 'self'",
    "form-action 'self'",
    "frame-ancestors 'none'",
    "upgrade-insecure-requests",
  ].join("; ");
}

/**
 * Levier de ROLLBACK opérationnel (#7650) : la CSP est ENFORCE par défaut.
 * Poser `CSP_REPORT_ONLY=true` (env Vercel) rebascule en Report-Only sans
 * redéploiement de code — à n'utiliser qu'en cas d'incident, et à retirer
 * après correction. L'ancien opt-in `CSP_ENFORCE` (jamais activé, constat de
 * l'audit #7650) disparaît : l'état sûr est le défaut.
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
