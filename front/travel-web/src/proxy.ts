import { NextResponse, type NextRequest } from "next/server";

import { buildCspDirectives, cspHeaderName, generateCspNonce } from "@/lib/csp";

/**
 * Proxy (convention Next 16 — ex-`middleware`, renommée par #7305 dans
 * front/web) : émission de la Content-Security-Policy ENFORCE avec nonce
 * par requête (issue #8022, tranche 1 — source unique des directives :
 * `src/lib/csp.ts`, pattern #7650 de front/web).
 *
 * Le nonce est posé sur les EN-TÊTES DE LA REQUÊTE : Next le lit dans
 * `content-security-policy` (cf. `parseRequestHeaders` →
 * `getScriptNonceFromHeader`) et l'appose sur ses propres scripts inline
 * (bootstrap, flight data) et preloads — possible parce que TOUTES les
 * routes HTML sont rendues dynamiquement (le root layout lit `cookies()`,
 * #7841). `x-nonce` sert aux composants serveur qui rendraient un script
 * inline explicite (aucun aujourd'hui).
 */
export function proxy(request: NextRequest): NextResponse {
  const nonce = generateCspNonce();
  const csp = buildCspDirectives({ nonce });
  const requestHeaders = new Headers(request.headers);
  requestHeaders.set("x-nonce", nonce);
  requestHeaders.set("content-security-policy", csp);

  const response = NextResponse.next({ request: { headers: requestHeaders } });
  // Enforce par défaut ; `CSP_REPORT_ONLY=true` = levier de rollback
  // (cf. src/lib/csp.ts).
  response.headers.set(cspHeaderName(), csp);

  return response;
}

export const config = {
  matcher: [
    // Toutes les routes HTML — JAMAIS l'API proxifiée `/api/*` (JSON, la
    // CSP n'y a pas de sens) ni les assets statiques de build.
    "/((?!api|_next/static|_next/image|favicon.ico|robots.txt|sitemap.xml).*)",
  ],
};
