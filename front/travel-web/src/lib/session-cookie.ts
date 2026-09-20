import type { NextRequest } from "next/server";

/**
 * Session du compte client grand public (issue #7841, pattern #1299 de
 * front/web) : le token Sanctum du guard dédié `travel_customer` vit dans un
 * cookie httpOnly posé/supprimé UNIQUEMENT côté serveur (route handlers
 * Next). Le JavaScript de la page ne voit jamais le token — fin du stockage
 * en localStorage (`travel-account-token`, purgé côté client, voir
 * account-provider.tsx).
 *
 * Module server-side only (importé par les route handlers et le proxy).
 */

export const SESSION_COOKIE_NAME = "travel_account_token";

/** 30 jours — aligné sur la durée de vie des tokens Sanctum (cf. #7491). */
export const SESSION_COOKIE_MAX_AGE = 60 * 60 * 24 * 30;

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
 * production/HTTPS, SameSite=Lax (le site public reçoit des navigations
 * top-level depuis des liens externes — emails de confirmation notamment),
 * path=/.
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
