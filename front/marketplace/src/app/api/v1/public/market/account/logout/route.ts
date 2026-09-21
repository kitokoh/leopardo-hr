import { cookies } from "next/headers";
import { NextRequest, NextResponse } from "next/server";

import { resolveBackendBaseUrl } from "@/lib/backend-url";
import {
  SESSION_COOKIE_NAME,
  sessionCookieOptions,
} from "@/lib/session-cookie";

/**
 * Issue #8022 — POST /api/v1/public/market/account/logout.
 * Révoque le jeton côté backend (Bearer lu depuis le cookie httpOnly, jamais
 * depuis le client) puis supprime le cookie `leopardo_marche_buyer_token`.
 *
 * Comme #7841 (front/travel-web) et #1299 (front/web) : le cookie local est
 * supprimé dans TOUS les cas, mais un échec de révocation backend reste
 * visible (502) pour permettre un nouvel essai — sinon l'ancien jeton
 * resterait rejouable jusqu'à expiration (7 j, #7979).
 */

const LOGOUT_TIMEOUT_MS = 15_000;

export async function POST(request: NextRequest): Promise<NextResponse> {
  const cookieStore = await cookies();
  const token = cookieStore.get(SESSION_COOKIE_NAME)?.value;

  let revocationFailed = false;
  if (token) {
    try {
      const backendResponse = await fetch(
        `${resolveBackendBaseUrl()}/public/market/account/logout`,
        {
          method: "POST",
          headers: {
            Accept: "application/json",
            Authorization: `Bearer ${token}`,
          },
          cache: "no-store",
          signal: AbortSignal.timeout(LOGOUT_TIMEOUT_MS),
        },
      );
      revocationFailed = !backendResponse.ok;
    } catch {
      revocationFailed = true;
    }
  }

  // Suppression du cookie httpOnly quoi qu'il arrive (maxAge 0).
  cookieStore.set(SESSION_COOKIE_NAME, "", sessionCookieOptions(request, 0));

  return NextResponse.json(
    revocationFailed
      ? { data: { logged_out: false }, error: "LOGOUT_REVOCATION_FAILED" }
      : { data: { logged_out: true } },
    {
      status: revocationFailed ? 502 : 200,
      headers: { "Cache-Control": "no-store" },
    },
  );
}
