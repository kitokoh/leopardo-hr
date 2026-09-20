import type { NextRequest, NextResponse } from "next/server";

import { relayAuthAndSetSessionCookie } from "../_lib/auth-session";

/**
 * Issue #7841 — POST /api/v1/public/travel/marketplace/account/login.
 * Route SPÉCIFIQUE qui prime sur le proxy générique `[...path]` : relaie les
 * identifiants au backend et pose le token Sanctum en cookie httpOnly
 * `travel_account_token` au lieu de le renvoyer au navigateur.
 */
export async function POST(request: NextRequest): Promise<NextResponse> {
  return relayAuthAndSetSessionCookie(request, "login");
}
