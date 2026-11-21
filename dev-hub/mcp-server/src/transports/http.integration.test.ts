import { afterAll, beforeAll, describe, expect, it, vi } from "vitest";
import { startHttp, type HttpHandle } from "./http.js";
import { ApiClient } from "../api-client.js";
import { loadAllTools, loadManifest } from "../manifest.js";

/**
 * Test d'intégration du transport Streamable HTTP (issue #9747).
 *
 * Contrairement aux tests unitaires de `buildServer`, ce test démarre un VRAI
 * serveur HTTP sur un port éphémère et parle MCP par-dessus le réseau local :
 * c'est le seul moyen de détecter une régression de handshake, de négociation
 * `Content-Type` ou de fermeture de session — les trois modes de panne qui ne
 * se voient qu'en production.
 *
 * Déterminisme : port `0` (le noyau en attribue un libre), aucun accès réseau
 * externe (`fetch` est mocké pour l'API Laravel), aucune attente arbitraire.
 */

const PROTOCOL_VERSION = "2025-06-18";

let handle: HttpHandle;
let baseUrl: string;
let fetchMock: ReturnType<typeof vi.fn>;

/**
 * `fetch` réel, capturé AVANT le stub : le test doit joindre son propre serveur
 * local en vrai, et ne mocker QUE les appels sortants vers l'API Laravel.
 * (Un `vi.stubGlobal("fetch")` global interceptait aussi les requêtes vers
 * 127.0.0.1 — le test ne testait alors plus rien du transport.)
 */
const realFetch = globalThis.fetch;

beforeAll(async () => {
  fetchMock = vi.fn(async () => new Response(JSON.stringify({ data: [] }), { status: 200 }));

  vi.stubGlobal("fetch", (input: RequestInfo | URL, init?: RequestInit) => {
    const url = typeof input === "string" ? input : input instanceof URL ? input.href : input.url;
    if (url.startsWith("http://127.0.0.1") || url.startsWith("http://localhost")) {
      return realFetch(input, init);
    }
    return fetchMock(input, init);
  });

  const api = new ApiClient({ baseUrl: "https://api.test", token: "tok" });
  handle = await startHttp(loadAllTools(), api, { port: 0, host: "127.0.0.1" });
  baseUrl = handle.url;
});

afterAll(async () => {
  await handle.close();
  vi.unstubAllGlobals();
});

/** Envoie une requête JSON-RPC MCP et renvoie la réponse décodée. */
async function rpc(body: Record<string, unknown>, sessionId?: string): Promise<Response> {
  const headers: Record<string, string> = {
    "content-type": "application/json",
    accept: "application/json, text/event-stream",
  };
  if (sessionId !== undefined) {
    headers["mcp-session-id"] = sessionId;
  }

  return fetch(`${baseUrl}/mcp`, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
  });
}

/** Décode une réponse MCP : JSON direct ou flux SSE (`data: {...}`). */
async function decode(response: Response): Promise<Record<string, unknown>> {
  const text = await response.text();
  const contentType = response.headers.get("content-type") ?? "";

  if (contentType.includes("text/event-stream")) {
    // Un flux SSE peut porter plusieurs trames ; on prend la première qui
    // contient un message JSON-RPC complet.
    for (const line of text.split("\n")) {
      if (!line.startsWith("data:")) {
        continue;
      }
      const raw = line.slice("data:".length).trim();
      if (raw === "" || raw === "[DONE]") {
        continue;
      }
      try {
        return JSON.parse(raw) as Record<string, unknown>;
      } catch {
        continue;
      }
    }
    throw new Error(`Flux SSE sans trame JSON exploitable : ${text.slice(0, 200)}`);
  }

  return JSON.parse(text) as Record<string, unknown>;
}

describe("transport Streamable HTTP — intégration (#9747)", () => {
  it("répond à la sonde /healthz sans exposer de donnée métier", async () => {
    const response = await fetch(`${baseUrl}/healthz`);

    expect(response.status).toBe(200);
    const payload = (await response.json()) as { status: string; tools: number };
    expect(payload.status).toBe("ok");
    expect(payload.tools).toBe(loadAllTools().length);
  });

  it("renvoie 404 sur un chemin inconnu", async () => {
    const response = await fetch(`${baseUrl}/inconnu`);
    expect(response.status).toBe(404);
  });

  it("négocie le handshake initialize", async () => {
    const response = await rpc({
      jsonrpc: "2.0",
      id: 1,
      method: "initialize",
      params: {
        protocolVersion: PROTOCOL_VERSION,
        capabilities: {},
        clientInfo: { name: "vitest", version: "1.0.0" },
      },
    });

    expect(response.status).toBe(200);
    const payload = await decode(response);
    const result = payload.result as { serverInfo: { name: string }; protocolVersion: string };

    expect(result.serverInfo.name).toBe("leopardo-mcp");
    expect(typeof result.protocolVersion).toBe("string");
  });

  it("liste les outils des deux sources (A3 + générés)", async () => {
    const response = await rpc({ jsonrpc: "2.0", id: 2, method: "tools/list", params: {} });

    expect(response.status).toBe(200);
    const payload = await decode(response);
    const tools = (payload.result as { tools: Array<{ name: string }> }).tools;

    const a3Names = loadManifest().tools.map((tool) => tool.name);
    for (const name of a3Names) {
      expect(tools.some((tool) => tool.name === name), `outil A3 absent : ${name}`).toBe(true);
    }
    expect(tools.length).toBe(loadAllTools().length);
  });

  it("exécute un outil read en relayant l'appel à l'API", async () => {
    fetchMock.mockClear();

    const response = await rpc({
      jsonrpc: "2.0",
      id: 3,
      method: "tools/call",
      params: { name: "team_overview", arguments: {} },
    });

    expect(response.status).toBe(200);
    const payload = await decode(response);
    expect(payload.error).toBeUndefined();
    expect(fetchMock).toHaveBeenCalledTimes(1);
  });

  it("refuse un outil write sans toucher à l'API", async () => {
    fetchMock.mockClear();

    const response = await rpc({
      jsonrpc: "2.0",
      id: 4,
      method: "tools/call",
      params: { name: "absence_decision", arguments: { absence_id: 1, decision: "approve" } },
    });

    expect(response.status).toBe(200);
    const payload = await decode(response);
    const result = payload.result as { isError?: boolean; content: Array<{ text: string }> };

    expect(result.isError).toBe(true);
    expect(result.content[0]!.text).toContain("PendingAction");
    expect(fetchMock).not.toHaveBeenCalled();
  });

  it("refuse un outil inconnu (fail-closed)", async () => {
    const response = await rpc({
      jsonrpc: "2.0",
      id: 5,
      method: "tools/call",
      params: { name: "outil_inexistant", arguments: {} },
    });

    expect(response.status).toBe(200);
    const payload = await decode(response);
    const result = payload.result as { isError?: boolean };
    expect(result.isError).toBe(true);
  });

  it("sert plusieurs requêtes successives sur le même serveur (stateless)", async () => {
    for (const id of [10, 11, 12]) {
      const response = await rpc({ jsonrpc: "2.0", id, method: "tools/list", params: {} });
      expect(response.status).toBe(200);
      const payload = await decode(response);
      expect((payload.result as { tools: unknown[] }).tools.length).toBeGreaterThan(0);
    }
  });
});
