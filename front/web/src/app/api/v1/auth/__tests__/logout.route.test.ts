/**
 * @jest-environment node
 */
import { NextRequest } from "next/server";
import { POST } from "../logout/route";

jest.mock("@/lib/backend-url", () => ({
  resolveBackendBaseUrl: jest.fn(() => "https://backend.example.com/api/v1"),
}));

const mockCookieStore = {
  get: jest.fn(),
  set: jest.fn(),
};

jest.mock("next/headers", () => ({
  cookies: jest.fn(() => mockCookieStore),
}));

const mockFetch = jest.fn();

describe("POST /api/v1/auth/logout", () => {
  beforeEach(() => {
    jest.clearAllMocks();
    global.fetch = mockFetch as unknown as typeof fetch;
    mockCookieStore.get.mockReturnValue({ value: "token-to-revoke" });
  });

  it("révoque le token backend puis efface le cookie", async () => {
    mockFetch.mockResolvedValueOnce(new Response(null, { status: 204 }));

    const response = await POST(
      new NextRequest("https://app.example.com/api/v1/auth/logout"),
    );

    expect(response.status).toBe(200);
    await expect(response.json()).resolves.toEqual({
      success: true,
      revoked: true,
    });
    expect(mockFetch).toHaveBeenCalledWith(
      "https://backend.example.com/api/v1/auth/logout",
      expect.objectContaining({
        method: "POST",
        headers: expect.objectContaining({
          Authorization: "Bearer token-to-revoke",
        }),
      }),
    );
    expect(mockCookieStore.set).toHaveBeenCalledWith(
      "leopardo_token",
      "",
      expect.objectContaining({ httpOnly: true, maxAge: 0, path: "/" }),
    );
  });

  it("signale l’échec de révocation tout en supprimant le cookie local", async () => {
    mockFetch.mockResolvedValueOnce(new Response(null, { status: 503 }));

    const response = await POST(
      new NextRequest("https://app.example.com/api/v1/auth/logout"),
    );

    expect(response.status).toBe(502);
    await expect(response.json()).resolves.toEqual({
      success: false,
      revoked: false,
      error: "LOGOUT_REVOCATION_FAILED",
    });
    expect(mockCookieStore.set).toHaveBeenCalledWith(
      "leopardo_token",
      "",
      expect.objectContaining({ maxAge: 0 }),
    );
  });

  it("ne contacte pas le backend sans cookie et confirme le nettoyage local", async () => {
    mockCookieStore.get.mockReturnValueOnce(undefined);

    const response = await POST(
      new NextRequest("https://app.example.com/api/v1/auth/logout"),
    );

    expect(response.status).toBe(200);
    await expect(response.json()).resolves.toEqual({
      success: true,
      revoked: true,
    });
    expect(mockFetch).not.toHaveBeenCalled();
    expect(mockCookieStore.set).toHaveBeenCalledWith(
      "leopardo_token",
      "",
      expect.objectContaining({ maxAge: 0 }),
    );
  });
});
