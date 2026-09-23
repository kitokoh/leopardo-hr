/**
 * ⚠️ SYNCHRONISATION MANUELLE (#7964) : ce fichier existe en DEUX copies
 * byte-identiques — front/travel-web/src/middleware.ts et
 * front/marketplace/src/middleware.ts. Toute modification doit être
 * reportée à l'identique sur l'autre copie (garde CI :
 * dev-hub/tools/check-front-shared-parity.sh).
 */
import { NextResponse, type NextRequest } from "next/server";

import { buildCspDirectives, generateCspNonce } from "@/lib/csp";

/**
 * #8022 (suivi #8006) — CSP ENFORCE avec nonce par requête, même mécanique
 * que le proxy de front/web (#7650) : le nonce est posé sur l'en-tête
 * `Content-Security-Policy` de la REQUÊTE (Next l'y lit et l'appose sur ses
 * scripts inline) et la même CSP est émise sur la RÉPONSE. `x-nonce` reste
 * disponible pour un composant serveur qui rendrait un script inline.
 */
export function middleware(request: NextRequest) {
  const nonce = generateCspNonce();
  const csp = buildCspDirectives({ nonce });

  const requestHeaders = new Headers(request.headers);
  requestHeaders.set("x-nonce", nonce);
  requestHeaders.set("Content-Security-Policy", csp);

  const response = NextResponse.next({
    request: { headers: requestHeaders },
  });
  response.headers.set("Content-Security-Policy", csp);

  return response;
}

export const config = {
  matcher: [
    /*
     * Toutes les routes HTML — on exclut les assets statiques (déjà couverts
     * par les autres headers de next.config.ts) et les prefetches.
     */
    {
      source:
        "/((?!_next/static|_next/image|favicon.ico|robots.txt|sitemap.xml|.*\\.(?:png|jpg|jpeg|gif|webp|svg|ico)).*)",
      missing: [
        { type: "header", key: "next-router-prefetch" },
        { type: "header", key: "purpose", value: "prefetch" },
      ],
    },
  ],
};
