import { describe, expect, it, vi } from "vitest";
import { ApiClient, ApiError } from "./api-client.js";

describe("ApiClient", () => {
  it("envoie le Bearer token et parse la réponse JSON", async () => {
    const fetchMock = vi.fn(async () =>
      new Response(JSON.stringify({ data: [{ id: 1 }] }), {
        status: 200,
        headers: { "content-type": "application/json" },
      }),
    );
    vi.stubGlobal("fetch", fetchMock);

    const client = new ApiClient({ baseUrl: "https://api.test", token: "tok" });
    const result = await client.request<{ data: unknown[] }>({ method: "GET", path: "/api/v1/employees" });

    expect(result.data).toHaveLength(1);
    const call = fetchMock.mock.calls[0] as unknown as [URL, RequestInit];
    expect(String(call[0])).toBe("https://api.test/api/v1/employees");
    expect((call[1].headers as Record<string, string>).authorization).toBe("Bearer tok");

    vi.unstubAllGlobals();
  });

  it("lève une ApiError sur 403 (fail-closed)", async () => {
    vi.stubGlobal(
      "fetch",
      vi.fn(async () => new Response(JSON.stringify({ message: "Forbidden" }), { status: 403 })),
    );

    const client = new ApiClient({ baseUrl: "https://api.test", token: "tok" });
    await expect(client.request({ method: "GET", path: "/api/v1/employees" })).rejects.toBeInstanceOf(ApiError);

    vi.unstubAllGlobals();
  });
});
