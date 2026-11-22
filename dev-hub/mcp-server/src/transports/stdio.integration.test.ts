import { afterAll, beforeAll, describe, expect, it } from "vitest";
import { execFileSync } from "node:child_process";
import { existsSync } from "node:fs";
import { createServer, type Server } from "node:http";
import { dirname, resolve } from "node:path";
import { fileURLToPath } from "node:url";
import { Client } from "@modelcontextprotocol/sdk/client/index.js";
import { StdioClientTransport } from "@modelcontextprotocol/sdk/client/stdio.js";
import { loadAllTools, loadManifest } from "../manifest.js";

/**
 * Test d'intégration du transport stdio (issue #9752).
 *
 * Contrairement aux tests unitaires de `buildServer`, ce test lance le VRAI
 * binaire compilé (`dist/index.js`) en sous-processus et lui parle MCP par
 * stdin/stdout, avec le VRAI client du SDK officiel. C'est le seul moyen de
 * détecter une régression de framing JSON-RPC, de handshake ou de fermeture de
 * flux sur le transport qu'utilisent réellement les IDE (Claude Desktop,
 * VS Code, Cursor) — une panne qui ne se verrait sinon qu'en production.
 *
 * L'API Laravel est remplacée par un VRAI serveur HTTP local (et non par un
 * `vi.stubGlobal("fetch")`) : un stub ne traverse pas la frontière du
 * sous-processus, le binaire enfant ferait alors de vrais appels réseau. Un
 * serveur local rend le test à la fois hermétique et fidèle — on observe les
 * requêtes réellement émises par le serveur MCP.
 *
 * Déterminisme : port éphémère (`0`), aucune attente arbitraire, aucun accès
 * réseau externe.
 */

const HERE = dirname(fileURLToPath(import.meta.url));
const SERVER_ROOT = resolve(HERE, "..", "..");
const BINARY = resolve(SERVER_ROOT, "dist", "index.js");

interface RecordedRequest {
  method: string;
  path: string;
  authorization: string | undefined;
}

let client: Client;
let transport: StdioClientTransport;
let apiServer: Server;
let apiUrl: string;
let requests: RecordedRequest[] = [];

beforeAll(async () => {
  // Le binaire doit exister : on échoue avec un message actionnable plutôt
  // qu'avec un ENOENT obscur au fond du transport.
  if (!existsSync(BINARY)) {
    execFileSync("npm", ["run", "build"], { cwd: SERVER_ROOT, stdio: "pipe" });
  }
  if (!existsSync(BINARY)) {
    throw new Error(
      `Binaire MCP absent après build : ${BINARY}. Lancer 'npm run build' dans dev-hub/mcp-server.`,
    );
  }

  // Faux backend Laravel : enregistre chaque requête, répond 200 { data: [] }.
  apiServer = createServer((req, res) => {
    requests.push({
      method: req.method ?? "",
      path: req.url ?? "",
      authorization: req.headers.authorization,
    });
    res.writeHead(200, { "content-type": "application/json" });
    res.end(JSON.stringify({ data: [] }));
  });

  await new Promise<void>((resolveListen) => {
    apiServer.listen(0, "127.0.0.1", resolveListen);
  });

  const address = apiServer.address();
  const port = typeof address === "object" && address !== null ? address.port : 0;
  apiUrl = `http://127.0.0.1:${port}`;

  transport = new StdioClientTransport({
    command: process.execPath,
    args: [BINARY, "--transport", "stdio"],
    env: {
      ...(process.env as Record<string, string>),
      LEOPARDO_API_URL: apiUrl,
      LEOPARDO_API_TOKEN: "tok-integration",
    },
    stderr: "pipe",
  });

  client = new Client({ name: "vitest-stdio", version: "1.0.0" }, { capabilities: {} });
  await client.connect(transport);
}, 60_000);

afterAll(async () => {
  // Fermeture propre : `close()` tue le sous-processus. Aucun process orphelin.
  await client?.close();
  await new Promise<void>((resolveClose) => {
    apiServer.close(() => resolveClose());
  });
});

describe("transport stdio — intégration avec un vrai client MCP (#9752)", () => {
  it("négocie le handshake initialize", () => {
    const info = client.getServerVersion();

    expect(info).toBeDefined();
    expect(info!.name).toBe("leopardo-mcp");
    expect(typeof info!.version).toBe("string");
  });

  it("liste les outils des deux sources (A3 + générés)", async () => {
    const { tools } = await client.listTools();

    const a3Names = loadManifest().tools.map((tool) => tool.name);
    for (const name of a3Names) {
      expect(tools.some((tool) => tool.name === name), `outil A3 absent : ${name}`).toBe(true);
    }
    expect(tools.length).toBe(loadAllTools().length);
  });

  it("expose des schémas JSON valides pour TOUS les outils", async () => {
    // Régression #9752 : `payroll_current_status` déclarait
    // `"properties": []` (tableau au lieu d'objet). Un client MCP strict
    // rejette alors l'INTÉGRALITÉ de `tools/list` — pas seulement cet outil.
    const { tools } = await client.listTools();

    for (const tool of tools) {
      expect(tool.inputSchema.type, `${tool.name} : inputSchema.type`).toBe("object");
      const properties = tool.inputSchema.properties;
      if (properties !== undefined) {
        expect(
          Array.isArray(properties),
          `${tool.name} : 'properties' doit être un objet JSON, pas un tableau`,
        ).toBe(false);
      }
    }
  });

  it("préserve le contrat A3 (schéma et description)", async () => {
    const { tools } = await client.listTools();
    const teamOverview = tools.find((tool) => tool.name === "team_overview");

    expect(teamOverview).toBeDefined();
    expect(teamOverview!.inputSchema.type).toBe("object");
    expect(teamOverview!.description.length).toBeGreaterThan(0);
  });

  it("liste les ressources et lit le catalogue", async () => {
    const { resources } = await client.listResources();
    expect(resources.some((resource) => resource.uri === "leopardo://tools/catalog")).toBe(true);

    const catalog = await client.readResource({ uri: "leopardo://tools/catalog" });
    const text = catalog.contents[0]!.text as string;
    expect(JSON.parse(text)).toHaveProperty("tools");
  });

  it("lit le schéma d'un outil via sa ressource", async () => {
    const result = await client.readResource({ uri: "leopardo://tools/team_overview/schema" });
    const payload = JSON.parse(result.contents[0]!.text as string) as {
      name: string;
      sensitivity: string;
    };

    expect(payload.name).toBe("team_overview");
    expect(payload.sensitivity).toBe("read");
  });

  it("exécute un outil read en relayant l'appel à l'API avec le Bearer", async () => {
    requests = [];

    const result = await client.callTool({ name: "team_overview", arguments: {} });

    expect(result.isError).toBeFalsy();
    expect(requests).toHaveLength(1);
    expect(requests[0]!.method).toBe("GET");
    expect(requests[0]!.path).toContain("/api/v1/employees");
    expect(requests[0]!.authorization).toBe("Bearer tok-integration");
  });

  it("refuse un outil write sans toucher à l'API", async () => {
    requests = [];

    const result = await client.callTool({
      name: "absence_decision",
      arguments: { absence_id: 1, decision: "approve" },
    });

    expect(result.isError).toBe(true);
    const text = (result.content as Array<{ type: string; text: string }>)[0]!.text;
    expect(text).toContain("PendingAction");
    expect(requests).toHaveLength(0);
  });

  it("refuse un outil inconnu (fail-closed)", async () => {
    const result = await client.callTool({ name: "outil_inexistant", arguments: {} });

    expect(result.isError).toBe(true);
  });

  it("sert plusieurs requêtes successives sur la même session", async () => {
    for (let index = 0; index < 3; index += 1) {
      const { tools } = await client.listTools();
      expect(tools.length).toBeGreaterThan(0);
    }
  });
});
