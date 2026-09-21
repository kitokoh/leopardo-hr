import type { NextRequest, NextResponse } from "next/server";

import { relayAuthAndSetSessionCookie } from "../_lib/auth-session";

/**
 * Issue #8022 — POST /api/v1/public/market/account/register.
 * Même contrat que la route login : relais backend + cookie httpOnly, jeton
 * retiré du payload renvoyé au navigateur.
 */
export async function POST(request: NextRequest): Promise<NextResponse> {
  return relayAuthAndSetSessionCookie(request, "register");
}
