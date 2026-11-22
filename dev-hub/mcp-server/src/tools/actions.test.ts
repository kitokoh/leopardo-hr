import { describe, expect, it, vi } from "vitest";
import { executeTool } from "./execute.js";
import { ApiClient } from "../api-client.js";
import { loadAllTools, loadManifest } from "../manifest.js";

/**
 * Outils d'ACTION (issue #9753).
 *
 * Un endpoint d'action (`/approve`, `/publish`, `/rotate`…) n'est JAMAIS
 * exécuté directement : `tools/call` crée une proposition
 * (`POST /api/v1/ai/actions/propose`) et renvoie l'identifiant à confirmer par
 * un humain. Ces tests verrouillent cette propriété — c'est la garantie que le
 * serveur MCP ne peut pas déclencher un effet métier sans confirmation.
 */

const api = new ApiClient({ baseUrl: "https://api.test", token: "tok" });

function actionTool() {
  const tool = loadAllTools().find((candidate) => candidate.route?.action === true);
  expect(tool, "aucun outil d'action dans le manifeste").toBeDefined();
  return tool!;
}

describe("outils d'action — proposition, jamais exécution (#9753)", () => {
  it("le manifeste contient des outils d'action marqués", () => {
    const actions = loadAllTools().filter((tool) => tool.route?.action === true);

    expect(actions.length).toBeGreaterThan(0);
    for (const tool of actions) {
      // Une action n'est jamais une lecture, même quand le verbe HTTP est GET.
      expect(tool.sensitivity, `${tool.name} doit être write`).toBe("write");
    }
  });

  it("route une action vers /ai/actions/propose et JAMAIS vers l'endpoint métier", async () => {
    const fetchMock = vi.fn(
      async () =>
        new Response(
          JSON.stringify({
            data: { status: "confirmation_required", pending_action_id: "uuid-1" },
          }),
          { status: 201 },
        ),
    );
    vi.stubGlobal("fetch", fetchMock);

    const tool = actionTool();
    const result = await executeTool(tool, { id: 1 }, api);

    expect(result.isError).toBeUndefined();
    expect(fetchMock).toHaveBeenCalledTimes(1);

    const [url, init] = fetchMock.mock.calls[0] as unknown as [URL, RequestInit];
    expect(String(url)).toContain("/api/v1/ai/actions/propose");
    // Le point critique : l'endpoint métier de l'outil n'est jamais atteint.
    expect(String(url)).not.toContain(tool.route!.path.replace("{", ""));
    expect(init.method).toBe("POST");

    const body = JSON.parse(String(init.body)) as { tool: string; arguments: unknown };
    expect(body.tool).toBe(tool.name);
    expect(body.arguments).toEqual({ id: 1 });
  });

  it("renvoie le pending_action_id au client", async () => {
    vi.stubGlobal(
      "fetch",
      vi.fn(
        async () =>
          new Response(
            JSON.stringify({
              data: { status: "confirmation_required", pending_action_id: "uuid-42" },
            }),
            { status: 201 },
          ),
      ),
    );

    const result = await executeTool(actionTool(), {}, api);
    const text = (result.content as Array<{ type: string; text: string }>)[0]!.text;

    expect(text).toContain("uuid-42");
    expect(text).toContain("confirmation_required");
    vi.unstubAllGlobals();
  });

  it("remonte un refus RBAC de l'API comme erreur, sans effet de bord", async () => {
    vi.stubGlobal(
      "fetch",
      vi.fn(
        async () =>
          new Response(JSON.stringify({ error: "AI_TOOL_PERMISSION_DENIED" }), { status: 403 }),
      ),
    );

    const result = await executeTool(actionTool(), {}, api);

    expect(result.isError).toBe(true);
    const text = (result.content as Array<{ type: string; text: string }>)[0]!.text;
    expect(text).toContain("403");
    vi.unstubAllGlobals();
  });

  it("un outil write NON marqué action reste refusé (pas de contournement)", async () => {
    const fetchMock = vi.fn();
    vi.stubGlobal("fetch", fetchMock);

    const a3Names = new Set(loadManifest().tools.map((tool) => tool.name));
    const plainWrite = loadAllTools().find(
      (tool) => !a3Names.has(tool.name) && tool.sensitivity === "write" && tool.route?.action !== true,
    );
    expect(plainWrite, "aucun outil write non-action").toBeDefined();

    const result = await executeTool(plainWrite!, {}, api);

    expect(result.isError).toBe(true);
    expect(fetchMock).not.toHaveBeenCalled();
    vi.unstubAllGlobals();
  });
});
