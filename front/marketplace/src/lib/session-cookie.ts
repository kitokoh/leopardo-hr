import type { NextRequest } from "next/server";

/**
 * Session du compte acheteur — Leopardo Marché (#8022, suivi #7979 ; pattern
 * #7841 de front/travel-web, lui-même issu de #1299 de front/web).
 *
 * Le jeton opaque (`mkb_…`) vit dans un cookie httpOnly posé/supprimé
 * UNIQUEMENT côté serveur (route handlers Next). Le JavaScript de la page ne
 * voit jamais le jeton — fin du stockage en localStorage
 * (`leopardo_marche_buyer`, purgé côté client, voir lib/buyer.ts). Une XSS ne
 * peut plus voler la session.
 *
 * Module server-side only (importé par les route handlers et le proxy).
 */

export const SESSION_COOKIE_NAME = "leopardo_marche_buyer_token";

/**
 * 7 jours — aligné sur le TTL des jetons acheteur côté
 * `RetailBuyerAccountService` (#7979 : réduit de 30 → 7 jours).
 */
export const SESSION_COOKIE_MAX_AGE = 60 * 60 * 24 * 7;

export function isSecureRequest(request: NextRequest): boolean {
  return (
    request.nextUrl.protocol === "https:" ||
    process.env.NODE_ENV === "production"
  );
}

type SessionCookieOptions = {
  httpOnly: true;
  secure: boolean;
  sameSite: "lax";
  maxAge: number;
  path: "/";
};

/**
 * Attributs du cookie de session : httpOnly (inaccessible au JS), Secure en
 * production/HTTPS, SameSite=Lax (le site reçoit des navigations top-level
 * depuis des liens externes — emails de confirmation notamment), path=/.
 */
export function sessionCookieOptions(
  request: NextRequest,
  maxAge: number = SESSION_COOKIE_MAX_AGE,
): SessionCookieOptions {
  return {
    httpOnly: true,
    secure: isSecureRequest(request),
    sameSite: "lax",
    maxAge,
    path: "/",
  };
}
