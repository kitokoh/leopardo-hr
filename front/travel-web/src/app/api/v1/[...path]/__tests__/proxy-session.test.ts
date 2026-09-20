/**
 * Issue #7841 — tests du proxy générique `/api/v1/[...path]` : le Bearer est
 * injecté depuis le cookie httpOnly UNIQUEMENT sur la sous-surface compte
 * client (+ création de réservation), et le header Authorization envoyé par
 * le navigateur n'est JAMAIS relayé (fail-closed).
 */
import { NextRequest } from "next/server";
import { beforeEach, describe, expect, it, vi } from "vitest";

import { GET, POST } from "../route";

vi.mock("@/lib/backend-url", () => ({
  resolveBackendBaseUrl: () => "https://backend.example.com/api/v1",
}));

const mockFetch = vi.fn();

function proxyContext(path: string[]) {
  return { params: Promise.resolve({ path }) };
}

function requestWithCookie(url: string, init?: RequestInit): NextRequest {
  const headers = new Headers(init?.headers);
  headers.set("cookie", "travel_account_token=cookie-session-token");
  return new NextRequest(url, {
    method: init?.method,
    body: init?.body as BodyInit | undefined,
    headers,
  });
}

beforeEach(() => {
  vi.clearAllMocks();
  vi.stubGlobal("fetch", mockFetch);
  mockFetch.mockResolvedValue(Response.json({ data: [] }));
});

describe("proxy /api/v1/[...path] — session en cookie httpOnly (#7841)", () => {
  it("injecte le Bearer du cookie sur la surface account/*", async () => {
    const path = ["public", "travel", "marketplace", "account", "me"];
    await GET(
      requestWithCookie("https://travel.example.com/api/v1/public/travel/marketplace/account/me"),
      proxyContext(path),
    );

    const call = mockFetch.mock.calls[0];
    expect(call).toBeDefined();
    const headers = (call?.[1] as { headers: Headers }).headers;
    expect(headers.get("authorization")).toBe("Bearer cookie-session-token");
    expect(headers.get("cookie")).toBeNull();
  });

  it("injecte le Bearer du cookie sur la création de réservation", async () => {
    const path = ["public", "travel", "marketplace", "bookings"];
    await POST(
      requestWithCookie(
        "https://travel.example.com/api/v1/public/travel/marketplace/bookings",
        { method: "POST", body: JSON.stringify({}) },
      ),
      proxyContext(path),
    );

    const headers = (mockFetch.mock.calls[0]?.[1] as { headers: Headers }).headers;
    expect(headers.get("authorization")).toBe("Bearer cookie-session-token");
  });

  it("n'injecte RIEN sur le reste de la surface publique", async () => {
    const path = ["public", "travel", "marketplace", "trips"];
    await GET(
      requestWithCookie("https://travel.example.com/api/v1/public/travel/marketplace/trips"),
      proxyContext(path),
    );

    const headers = (mockFetch.mock.calls[0]?.[1] as { headers: Headers }).headers;
    expect(headers.get("authorization")).toBeNull();
    expect(headers.get("cookie")).toBeNull();
  });

  it("ne relaie jamais un Authorization fourni par le navigateur", async () => {
    const path = ["public", "travel", "marketplace", "account", "me"];
    await GET(
      new NextRequest(
        "https://travel.example.com/api/v1/public/travel/marketplace/account/me",
        { headers: { authorization: "Bearer js-forged-token" } },
      ),
      proxyContext(path),
    );

    const headers = (mockFetch.mock.calls[0]?.[1] as { headers: Headers }).headers;
    expect(headers.get("authorization")).toBeNull();
  });

  it("refuse toujours les chemins hors public/travel (fail-closed)", async () => {
    const response = await GET(
      requestWithCookie("https://travel.example.com/api/v1/auth/login"),
      proxyContext(["auth", "login"]),
    );

    expect(response.status).toBe(404);
    expect(mockFetch).not.toHaveBeenCalled();
  });
});
