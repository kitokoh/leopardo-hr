/**
 * @jest-environment node
 */
import { NextRequest } from "next/server";
import { POST } from "../login-code/verify/route";

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

function verifyRequest(body: unknown): NextRequest {
  return new NextRequest(
    "https://app.example.com/api/v1/auth/login-code/verify",
    {
      method: "POST",
      body: JSON.stringify(body),
      headers: { "Content-Type": "application/json" },
    },
  );
}

describe("POST /api/v1/auth/login-code/verify (#7490)", () => {
  beforeEach(() => {
    jest.clearAllMocks();
    global.fetch = mockFetch as unknown as typeof fetch;
  });

  it("pose le token en cookie httpOnly et ne le renvoie JAMAIS au navigateur", async () => {
    mockFetch.mockResolvedValueOnce(
      new Response(
        JSON.stringify({
          data: { id: 1, email: "founder@newtech.dz" },
          token: "1|secret-plain-text-token",
          token_type: "Bearer",
        }),
        { status: 200 },
      ),
    );

    const response = await POST(
      verifyRequest({ email: "founder@newtech.dz", code: "123456" }),
    );

    expect(response.status).toBe(200);
    const payload = await response.json();
    expect(payload.token).toBeUndefined();
    expect(payload.data).toEqual({ id: 1, email: "founder@newtech.dz" });

    expect(mockFetch).toHaveBeenCalledWith(
      "https://backend.example.com/api/v1/auth/login-code/verify",
      expect.objectContaining({ method: "POST" }),
    );
    expect(mockCookieStore.set).toHaveBeenCalledWith(
      "leopardo_token",
      "1|secret-plain-text-token",
      expect.objectContaining({ httpOnly: true, sameSite: "strict", path: "/" }),
    );
  });

  it("relaye l'erreur backend telle quelle (code invalide) sans poser de cookie", async () => {
    mockFetch.mockResolvedValueOnce(
      new Response(
        JSON.stringify({ success: false, error: "LOGIN_CODE_INVALID" }),
        { status: 400 },
      ),
    );

    const response = await POST(
      verifyRequest({ email: "founder@newtech.dz", code: "000000" }),
    );

    expect(response.status).toBe(400);
    await expect(response.json()).resolves.toEqual({
      success: false,
      error: "LOGIN_CODE_INVALID",
    });
    expect(mockCookieStore.set).not.toHaveBeenCalled();
  });

  it("relaye le verrou anti-brute-force (429)", async () => {
    mockFetch.mockResolvedValueOnce(
      new Response(
        JSON.stringify({ success: false, error: "LOGIN_CODE_TOO_MANY_ATTEMPTS" }),
        { status: 429 },
      ),
    );

    const response = await POST(
      verifyRequest({ email: "founder@newtech.dz", code: "000000" }),
    );

    expect(response.status).toBe(429);
    expect(mockCookieStore.set).not.toHaveBeenCalled();
  });

  it("répond 400 sur un corps non-JSON", async () => {
    const response = await POST(
      new NextRequest(
        "https://app.example.com/api/v1/auth/login-code/verify",
        { method: "POST", body: "not-json" },
      ),
    );

    expect(response.status).toBe(400);
    expect(mockFetch).not.toHaveBeenCalled();
  });
});
