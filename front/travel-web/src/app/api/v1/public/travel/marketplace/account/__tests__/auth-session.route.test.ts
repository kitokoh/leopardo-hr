/**
 * Issue #7841 — tests des route handlers d'auth du compte client : le token
 * Sanctum doit finir en cookie httpOnly `travel_account_token` et ne JAMAIS
 * apparaître dans le JSON renvoyé au navigateur.
 */
import { NextRequest } from "next/server";
import { beforeEach, describe, expect, it, vi } from "vitest";

import { POST as loginPost } from "../login/route";
import { POST as registerPost } from "../register/route";

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

function jsonRequest(url: string, body: unknown): NextRequest {
  return new NextRequest(url, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(body),
  });
}

beforeEach(() => {
  vi.clearAllMocks();
  vi.stubGlobal("fetch", mockFetch);
});

describe("POST /api/v1/public/travel/marketplace/account/login", () => {
  it("pose le token en cookie httpOnly et le retire du payload client", async () => {
    mockFetch.mockResolvedValueOnce(
      Response.json({
        data: {
          account: { id: 1, name: "Awa", email: "awa@example.com" },
          token: "sanctum-secret-token",
        },
      }),
    );

    const response = await loginPost(
      jsonRequest("https://travel.example.com/api/v1/public/travel/marketplace/account/login", {
        email: "awa@example.com",
        password: "secret",
      }),
    );

    expect(response.status).toBe(200);
    const payload = (await response.json()) as { data: Record<string, unknown> };
    expect(payload.data.account).toEqual({
      id: 1,
      name: "Awa",
      email: "awa@example.com",
    });
    // Le token ne doit jamais atteindre le JS client.
    expect(payload.data.token).toBeUndefined();
    expect(JSON.stringify(payload)).not.toContain("sanctum-secret-token");

    expect(mockFetch).toHaveBeenCalledWith(
      "https://backend.example.com/api/v1/public/travel/marketplace/account/login",
      expect.objectContaining({ method: "POST" }),
    );
    expect(mockCookieStore.set).toHaveBeenCalledWith(
      "travel_account_token",
      "sanctum-secret-token",
      expect.objectContaining({
        httpOnly: true,
        secure: true,
        sameSite: "lax",
        path: "/",
        maxAge: 60 * 60 * 24 * 30,
      }),
    );
  });

  it("relaie les erreurs backend sans poser de cookie", async () => {
    mockFetch.mockResolvedValueOnce(
      Response.json({ message: "Identifiants invalides." }, { status: 401 }),
    );

    const response = await loginPost(
      jsonRequest("https://travel.example.com/api/v1/public/travel/marketplace/account/login", {
        email: "awa@example.com",
        password: "wrong",
      }),
    );

    expect(response.status).toBe(401);
    await expect(response.json()).resolves.toEqual({
      message: "Identifiants invalides.",
    });
    expect(mockCookieStore.set).not.toHaveBeenCalled();
  });

  it("répond 400 sur un corps non-JSON sans contacter le backend", async () => {
    const response = await loginPost(
      new NextRequest(
        "https://travel.example.com/api/v1/public/travel/marketplace/account/login",
        { method: "POST", body: "not-json" },
      ),
    );

    expect(response.status).toBe(400);
    expect(mockFetch).not.toHaveBeenCalled();
    expect(mockCookieStore.set).not.toHaveBeenCalled();
  });

  it("répond 502 JSON quand le backend est injoignable", async () => {
    mockFetch.mockRejectedValueOnce(new TypeError("fetch failed"));

    const response = await loginPost(
      jsonRequest("https://travel.example.com/api/v1/public/travel/marketplace/account/login", {
        email: "awa@example.com",
        password: "secret",
      }),
    );

    expect(response.status).toBe(502);
    expect(mockCookieStore.set).not.toHaveBeenCalled();
  });
});

describe("POST /api/v1/public/travel/marketplace/account/register", () => {
  it("pose le cookie de session et conserve claimed_bookings sans le token", async () => {
    mockFetch.mockResolvedValueOnce(
      Response.json({
        data: {
          account: { id: 2, name: "Moussa", email: "moussa@example.com" },
          token: "register-secret-token",
          claimed_bookings: 3,
        },
      }),
    );

    const response = await registerPost(
      jsonRequest(
        "https://travel.example.com/api/v1/public/travel/marketplace/account/register",
        { name: "Moussa", email: "moussa@example.com", password: "secret" },
      ),
    );

    expect(response.status).toBe(200);
    const payload = (await response.json()) as { data: Record<string, unknown> };
    expect(payload.data.claimed_bookings).toBe(3);
    expect(payload.data.token).toBeUndefined();
    expect(mockCookieStore.set).toHaveBeenCalledWith(
      "travel_account_token",
      "register-secret-token",
      expect.objectContaining({ httpOnly: true, sameSite: "lax" }),
    );
  });
});
