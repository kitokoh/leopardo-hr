import type { NextRequest, NextResponse } from "next/server";

import { relayAuthAndSetSessionCookie } from "../_lib/auth-session";

/**
 * Issue #7841 — POST /api/v1/public/travel/marketplace/account/register.
 * Comme le login : l'inscription renvoie aussi un token Sanctum, posé ici en
 * cookie httpOnly `travel_account_token` et retiré du payload client.
 */
export async function POST(request: NextRequest): Promise<NextResponse> {
  return relayAuthAndSetSessionCookie(request, "register");
}
