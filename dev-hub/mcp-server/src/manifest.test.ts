import { describe, expect, it } from "vitest";
import { loadAllTools, loadManifest, ManifestSchema } from "./manifest.js";
import { TOOL_ROUTES } from "./tools/execute.js";
import { listResources, readResource } from "./resources.js";

describe("manifest", () => {
  it("charge le manifeste versionné et le valide", () => {
    const manifest = loadManifest();
    expect(manifest.schema_version).toBe(1);
    expect(manifest.tools.length).toBeGreaterThan(0);
  });

  it("rejette un manifeste invalide", () => {
    expect(() => ManifestSchema.parse({ schema_version: 2, tools: [] })).toThrow();
  });

  it("chaque outil du manifeste A3 a une route REST mappée", () => {
    const manifest = loadManifest();
    for (const tool of manifest.tools) {
      expect(TOOL_ROUTES[tool.name], `route manquante pour ${tool.name}`).toBeDefined();
    }
  });

  it("charge et fusionne les deux sources d'outils (#9746)", () => {
    const a3 = loadManifest().tools;
    const all = loadAllTools();

    expect(all.length).toBeGreaterThan(a3.length);
    for (const tool of a3) {
      expect(all.some((candidate) => candidate.name === tool.name)).toBe(true);
    }
    // Tri déterministe : `tools/list` doit être stable d'un démarrage à l'autre.
    expect(all.map((tool) => tool.name)).toEqual([...all.map((tool) => tool.name)].sort((a, b) => a.localeCompare(b)));
  });

  it("le contrat A3 gagne sur une définition générée de même nom (#9746)", () => {
    const a3 = loadManifest().tools;
    const all = loadAllTools();
    const byName = new Map(all.map((tool) => [tool.name, tool]));

    for (const tool of a3) {
      expect(byName.get(tool.name)).toEqual(tool);
    }
  });

  it("tout outil généré porte une route exécutable et une sensibilité cohérente (#9746)", () => {
    const a3Names = new Set(loadManifest().tools.map((tool) => tool.name));
    const generated = loadAllTools().filter((tool) => !a3Names.has(tool.name));

    expect(generated.length).toBeGreaterThan(0);
    for (const tool of generated) {
      expect(tool.route, `route absente pour ${tool.name}`).toBeDefined();
      expect(tool.route!.path.startsWith("/api/v1/")).toBe(true);
      if (tool.route!.action === true) {
        // Issue #9753 — une action est TOUJOURS `write`, même en GET
        // (`/departure`, `/preview`) : elle n'est jamais exécutée directement.
        expect(tool.sensitivity, `${tool.name} : action non-write`).toBe("write");
      } else {
        expect(tool.sensitivity).toBe(tool.route!.method === "GET" ? "read" : "write");
      }
    }
  });

  it("les outils write/send ne sont jamais exécutés directement", () => {
    const manifest = loadManifest();
    const sensitive = manifest.tools.filter((t) => t.sensitivity !== "read");
    expect(sensitive.length).toBeGreaterThan(0);
    for (const tool of sensitive) {
      expect(["write", "send"]).toContain(tool.sensitivity);
    }
  });
});

describe("resources", () => {
  it("expose le catalogue et un schéma par outil", () => {
    const tools = loadAllTools();
    const resources = listResources(tools);
    expect(resources).toHaveLength(tools.length + 1);
    expect(resources[0]!.uri).toBe("leopardo://tools/catalog");
  });

  it("lit le catalogue et le schéma d'un outil", () => {
    const tools = loadAllTools();
    const catalog = readResource("leopardo://tools/catalog", tools);
    expect(catalog.contents[0]!.text).toContain("tools");

    const first = tools[0]!;
    const schema = readResource(`leopardo://tools/${first.name}/schema`, tools);
    expect(schema.contents[0]!.text).toContain(first.name);
  });

  it("rejette une ressource inconnue", () => {
    const tools = loadAllTools();
    expect(() => readResource("leopardo://tools/inexistant/schema", tools)).toThrow();
  });
});
