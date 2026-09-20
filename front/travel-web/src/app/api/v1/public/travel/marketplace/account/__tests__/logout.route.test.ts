/**
 * Issue #7841 — tests du route handler de déconnexion : révocation backend
 * (Bearer lu depuis le cookie httpOnly, jamais depuis le client) puis
 * suppression du cookie `travel_account_token` dans tous les cas.
 */
import { NextRequest } from "next/server";
import { beforeEach, describe, expect, it, vi } from "vitest";

import { POST } from "../logout/route";

vi.mock("@/lib/backend-url", () => ({
  resolveBackendBaseUrl: () => "https://backend.example.com/api/v1",
}));

const mockCookieStore = {
  get: vi.fn(),
  set: vi.fn(),
};

vi.mock("next/headers", () => ({
  cookies: vi.fn(async () => mockCookieStore),
}));

const mockFetch = vi.fn();

const LOGOUT_URL =
  "https://travel.example.com/api/v1/public/travel/marketplace/account/logout";

beforeEach(() => {
  vi.clearAllMocks();
  vi.stubGlobal("fetch", mockFetch);
  mockCookieStore.get.mockReturnValue({ value: "token-to-revoke" });
});

describe("POST /api/v1/public/travel/marketplace/account/logout", () => {
  it("révoque le token backend puis supprime le cookie httpOnly", async () => {
    mockFetch.mockResolvedValueOnce(
      Response.json({ data: { logged_out: true } }),
    );

    const response = await POST(new NextRequest(LOGOUT_URL, { method: "POST" }));

    expect(response.status).toBe(200);
    await expect(response.json()).resolves.toEqual({
      data: { logged_out: true },
    });
    expect(mockFetch).toHaveBeenCalledWith(
      "https://backend.example.com/api/v1/public/travel/marketplace/account/logout",
      expect.objectContaining({
        method: "POST",
        headers: expect.objectContaining({
          Authorization: "Bearer token-to-revoke",
        }),
      }),
    );
    expect(mockCookieStore.set).toHaveBeenCalledWith(
      "travel_account_token",
      "",
      expect.objectContaining({ httpOnly: true, maxAge: 0, path: "/" }),
    );
  });

  it("signale l'échec de révocation (502) tout en supprimant le cookie", async () => {
    mockFetch.mockResolvedValueOnce(new Response(null, { status: 503 }));

    const response = await POST(new NextRequest(LOGOUT_URL, { method: "POST" }));

    expect(response.status).toBe(502);
    await expect(response.json()).resolves.toEqual({
      data: { logged_out: false },
      error: "LOGOUT_REVOCATION_FAILED",
    });
    expect(mockCookieStore.set).toHaveBeenCalledWith(
      "travel_account_token",
      "",
      expect.objectContaining({ maxAge: 0 }),
    );
  });

  it("ne contacte pas le backend sans cookie et confirme le nettoyage local", async () => {
    mockCookieStore.get.mockReturnValueOnce(undefined);

    const response = await POST(new NextRequest(LOGOUT_URL, { method: "POST" }));

    expect(response.status).toBe(200);
    expect(mockFetch).not.toHaveBeenCalled();
    expect(mockCookieStore.set).toHaveBeenCalledWith(
      "travel_account_token",
      "",
      expect.objectContaining({ maxAge: 0 }),
    );
  });
});
