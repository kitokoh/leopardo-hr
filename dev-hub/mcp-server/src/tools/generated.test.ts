import { describe, expect, it, vi } from "vitest";
import { routeFromManifest, executeTool } from "./execute.js";
import { ApiClient } from "../api-client.js";
import { loadAllTools, loadManifest } from "../manifest.js";

/**
 * Outils GÉNÉRÉS depuis `api/openapi.yaml` (issue #9746).
 *
 * Ces tests verrouillent le contrat du pont : une route déclarative doit
 * produire exactement la requête REST attendue, et un outil `write` généré ne
 * doit JAMAIS atteindre l'API.
 */

describe("routeFromManifest", () => {
  it("substitue les paramètres de chemin", () => {
    const route = routeFromManifest({
      method: "GET",
      path: "/api/v1/tasks/{task}",
      pathParams: ["task"],
      queryParams: [],
      hasBody: false,
    });

    expect(route.path({ task: 42 })).toBe("/api/v1/tasks/42");
  });

  it("encode les paramètres de chemin (pas d'injection d'URL)", () => {
    const route = routeFromManifest({
      method: "GET",
      path: "/api/v1/tasks/{task}",
      pathParams: ["task"],
      queryParams: [],
      hasBody: false,
    });

    expect(route.path({ task: "a/../b" })).toBe("/api/v1/tasks/a%2F..%2Fb");
  });

  it("refuse un paramètre de chemin manquant au lieu de produire 'undefined'", () => {
    const route = routeFromManifest({
      method: "GET",
      path: "/api/v1/tasks/{task}",
      pathParams: ["task"],
      queryParams: [],
      hasBody: false,
    });

    expect(() => route.path({})).toThrow(/Paramètre de chemin manquant/);
  });

  it("ne transmet que les paramètres de requête déclarés et typés", () => {
    const route = routeFromManifest({
      method: "GET",
      path: "/api/v1/tasks",
      pathParams: [],
      queryParams: ["status", "project_id"],
      hasBody: false,
    });

    expect(route.query!({ status: "todo", project_id: 3, intrus: "x" })).toEqual({
      status: "todo",
      project_id: 3,
    });
    expect(route.query!({ status: { nested: true } })).toEqual({});
  });

  it("transmet le corps tel quel quand la route en attend un", () => {
    const route = routeFromManifest({
      method: "POST",
      path: "/api/v1/tasks",
      pathParams: [],
      queryParams: [],
      hasBody: true,
    });

    expect(route.body!({ body: { title: "T" } })).toEqual({ title: "T" });
    expect(route.body!({})).toEqual({});
  });
});

describe("executeTool — outils générés (#9746)", () => {
  const api = new ApiClient({ baseUrl: "https://api.test", token: "tok" });

  function generatedTool(sensitivity: "read" | "write") {
    const a3Names = new Set(loadManifest().tools.map((tool) => tool.name));
    const tool = loadAllTools().find(
      (candidate) => !a3Names.has(candidate.name) && candidate.sensitivity === sensitivity,
    );
    expect(tool, `aucun outil généré de sensibilité ${sensitivity}`).toBeDefined();
    return tool!;
  }

  it("exécute un outil généré read via l'API", async () => {
    const fetchMock = vi.fn(async () => new Response(JSON.stringify({ data: [] }), { status: 200 }));
    vi.stubGlobal("fetch", fetchMock);

    const tool = generatedTool("read");
    const args: Record<string, unknown> = {};
    for (const param of tool.route!.pathParams) {
      args[param] = 1;
    }

    const result = await executeTool(tool, args, api);

    expect(result.isError).toBeUndefined();
    expect(fetchMock).toHaveBeenCalledTimes(1);
    const [url, init] = fetchMock.mock.calls[0] as unknown as [URL, RequestInit];
    expect(String(url)).toContain(tool.route!.path.replace(/\{[^}]+\}/g, "1"));
    expect(init.method).toBe("GET");
    vi.unstubAllGlobals();
  });

  it("refuse un outil généré write sans aucun appel API", async () => {
    const fetchMock = vi.fn();
    vi.stubGlobal("fetch", fetchMock);

    const tool = generatedTool("write");
    const result = await executeTool(tool, {}, api);

    expect(result.isError).toBe(true);
    expect(fetchMock).not.toHaveBeenCalled();
    vi.unstubAllGlobals();
  });

  it("refuse un outil généré dont la route est absente (fail-closed)", async () => {
    const tool = { ...generatedTool("read") };
    delete (tool as { route?: unknown }).route;

    const result = await executeTool(tool, {}, api);

    expect(result.isError).toBe(true);
  });
});
