/**
 * Security fix (#1299): Override logout to clear the httpOnly session cookie.
 *
 * Forwards the logout to Laravel (invalidates the Sanctum token server-side),
 * then removes the `leopardo_token` cookie from the browser so the session
 * cannot be replayed after logout.
 */

import { cookies } from "next/headers";
import { NextRequest, NextResponse } from "next/server";

import { resolveBackendBaseUrl } from "@/lib/backend-url";
const COOKIE_NAME = "leopardo_token";

// resolveBackendBaseUrl importé depuis @/lib/backend-url (audit #1701)

export async function POST(request: NextRequest): Promise<NextResponse> {
  const cookieStore = await cookies();
  const token = cookieStore.get(COOKIE_NAME)?.value;

  // Révoquer le token côté backend avant de confirmer la déconnexion.
  // Le cookie est tout de même supprimé localement dans tous les cas, mais une
  // erreur réseau ou une réponse non-2xx doit rester visible pour permettre un
  // nouvel essai : sinon l’ancien Bearer token resterait rejouable.
  let revocationFailed = false;
  if (token) {
    try {
      const backendResponse = await fetch(
        `${resolveBackendBaseUrl()}/auth/logout`,
        {
          method: "POST",
          headers: {
            Accept: "application/json",
            Authorization: `Bearer ${token}`,
          },
          cache: "no-store",
        },
      );
      revocationFailed = !backendResponse.ok;
    } catch {
      revocationFailed = true;
    }
  }

  // Always clear the httpOnly cookie regardless of backend response
  cookieStore.set(COOKIE_NAME, "", {
    httpOnly: true,
    secure:
      request.nextUrl.protocol === "https:" ||
      process.env.NODE_ENV === "production",
    sameSite: "strict",
    maxAge: 0, // Expire immediately
    path: "/",
  });

  return NextResponse.json(
    revocationFailed
      ? { success: false, revoked: false, error: "LOGOUT_REVOCATION_FAILED" }
      : { success: true, revoked: true },
    {
      status: revocationFailed ? 502 : 200,
      headers: { "Cache-Control": "no-store" },
    },
  );
}
