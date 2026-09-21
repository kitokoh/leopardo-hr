import { NextResponse, type NextRequest } from "next/server";

import { buildCspDirectives, cspHeaderName, generateCspNonce } from "@/lib/csp";

/**
 * Proxy (convention Next 16, ex-`middleware`) — émission de la
 * Content-Security-Policy ENFORCE avec nonce par requête (#8022, pattern
 * #7650 de front/web ; source unique des directives : `src/lib/csp.ts`).
 *
 * Le nonce est posé sur les EN-TÊTES DE LA REQUÊTE : Next le lit dans
 * `content-security-policy` et l'appose sur ses propres scripts inline
 * (bootstrap, flight data) et preloads — possible parce que le root layout
 * lit `cookies()` (#7841), ce qui rend toutes les routes HTML dynamiques.
 * `x-nonce` sert aux composants serveur qui rendraient un script inline
 * explicite (aucun aujourd'hui — garde-fou pour la suite).
 */

export function proxy(request: NextRequest): NextResponse {
  const nonce = generateCspNonce();
  const csp = buildCspDirectives({ nonce });
  const requestHeaders = new Headers(request.headers);
  requestHeaders.set("x-nonce", nonce);
  requestHeaders.set("content-security-policy", csp);

  const response = NextResponse.next({ request: { headers: requestHeaders } });
  // Enforce par défaut ; `CSP_REPORT_ONLY=true` = levier de rollback (#7650).
  response.headers.set(cspHeaderName(), csp);

  return response;
}

export const config = {
  matcher: [
    // Toutes les routes HTML hors assets statiques et internals Next.
    "/((?!_next/static|_next/image|favicon.ico|robots.txt|sitemap.xml|manifest.webmanifest|icons/|.*\\.(?:svg|png|jpg|jpeg|gif|webp|avif|ico|css|js|map|txt|xml|woff2?)$).*)",
  ],
};
