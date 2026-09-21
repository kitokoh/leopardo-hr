import type { NextRequest, NextResponse } from "next/server";

import { relayAuthAndSetSessionCookie } from "../_lib/auth-session";

/**
 * Issue #8022 — POST /api/v1/public/market/account/login.
 * Route SPÉCIFIQUE qui prime sur le proxy générique `[...path]` : relaie les
 * identifiants au backend et pose le jeton acheteur en cookie httpOnly
 * `leopardo_marche_buyer_token` au lieu de le renvoyer au navigateur.
 */
export async function POST(request: NextRequest): Promise<NextResponse> {
  return relayAuthAndSetSessionCookie(request, "login");
}
