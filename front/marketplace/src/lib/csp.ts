/**
 * Content-Security-Policy à nonce par requête — tranche #8022 (suivi #8006).
 *
 * Avant cette tranche, la CSP vivait en header STATIQUE dans
 * `next.config.ts` (#7980) avec `script-src 'unsafe-inline'` : une XSS
 * inline s'exécutait toujours. Même bascule que front/web (#7650) : la CSP
 * est construite ICI, émise par `src/middleware.ts` avec un nonce par
 * requête + `'strict-dynamic'` — plus aucun `'unsafe-inline'` en
 * script-src. Contrepartie assumée : le root layout lit `headers()` pour
 * forcer le rendu dynamique de toutes les routes HTML (un nonce est par
 * définition par-requête — même compromis que front/web #3807/#7650).
 *
 * style-src conserve `'unsafe-inline'` (Tailwind) — le vecteur XSS
 * exécutable est script-src, comme acté par #7650.
 */

export function resolveApiOrigin(): string {
  // NEXT_PUBLIC_MARKET_API_BASE est OBLIGATOIRE au build (#7963) — pas de
  // repli en dur ici, cohérent avec le reste de l'app.
  const raw = process.env.NEXT_PUBLIC_MARKET_API_BASE;
  if (!raw) return "";
  try {
    return new URL(raw).origin;
  } catch {
    return "";
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
    // Visuels produits : URL absolues arbitraires des vendeurs (cf.
    // next.config.ts) — https: reste nécessaire.
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
