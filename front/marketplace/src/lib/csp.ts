/**
 * Content-Security-Policy à nonce strict — source UNIQUE des directives
 * (issue #8022, tranche 1 — pattern #7650 de front/web, adapté à Leopardo
 * Marché : aucune origine script tierce, aucune analytics).
 *
 * Historique : la CSP vivait en statique dans `next.config.ts` (#7980) avec
 * `script-src 'self' 'unsafe-inline'` — une XSS inline s'exécutait donc
 * toujours (constat central de #8022, aggravé par la session acheteur
 * alors lisible en localStorage — tranche 2). Un nonce est par définition
 * PAR REQUÊTE, or les headers de `next.config.ts` sont statiques : la
 * politique est désormais construite ICI et émise par le proxy
 * (`src/proxy.ts`) en mode enforce, avec un nonce régénéré à chaque
 * requête. Ne PAS réintroduire de CSP dans `next.config.ts` ou
 * `vercel.json` : deux politiques enforce s'intersectent et la copie
 * statique (sans nonce) bloquerait tout script.
 *
 * Pourquoi ça tient : le root layout déclare `dynamic = "force-dynamic"`
 * (#8022) — TOUTES les routes HTML sont rendues dynamiquement, et Next lit
 * le nonce dans l'en-tête `Content-Security-Policy` de la REQUÊTE (cf.
 * `parseRequestHeaders` → `getScriptNonceFromHeader`) pour l'apposer
 * lui-même sur ses scripts inline (bootstrap, flight data) et ses preloads.
 * Les pages catalogue étaient DÉJÀ `force-dynamic` (données API en
 * `no-store`) : le coût de rendu ne change pas, seules les pages clientes
 * statiques basculent en SSR.
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

/**
 * Origine API pour connect-src — même contrat que `api.ts` (#7963) :
 * `NEXT_PUBLIC_MARKET_API_BASE` est OBLIGATOIRE partout (aucun repli en
 * dur, jamais de cible distante implicite) ; seul `NODE_ENV === 'test'`
 * tolère un repli localhost, signalé par un `console.warn`.
 */
const TEST_FALLBACK_ORIGIN = "http://localhost:8000";

let warnedTestFallback = false;

export function resolveApiOrigin(): string {
  const configured = process.env.NEXT_PUBLIC_MARKET_API_BASE?.trim();
  if (configured) {
    try {
      return new URL(configured).origin;
    } catch {
      throw new Error(
        `[csp] NEXT_PUBLIC_MARKET_API_BASE invalide (« ${configured} »). ` +
          "Définissez une URL absolue valide (ex. https://api.exemple.com) " +
          "— aucun repli en dur (#7963).",
      );
    }
  }
  if (process.env.NODE_ENV === "test") {
    if (!warnedTestFallback) {
      warnedTestFallback = true;
      console.warn(
        "[csp] NEXT_PUBLIC_MARKET_API_BASE absente — repli TEST sur " +
          `${TEST_FALLBACK_ORIGIN} (interdit hors NODE_ENV=test, #7963).`,
      );
    }
    return TEST_FALLBACK_ORIGIN;
  }
  throw new Error(
    "[csp] NEXT_PUBLIC_MARKET_API_BASE absente. Définissez " +
      "NEXT_PUBLIC_MARKET_API_BASE (ex. https://api.exemple.com) dans " +
      "l'environnement — runtime ET build (aucun repli en dur, #7963).",
  );
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
