import { describe, expect, it } from "vitest";
import { loadManifest, ManifestSchema } from "./manifest.js";
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

  it("chaque outil du manifeste a une route REST mappée", () => {
    const manifest = loadManifest();
    for (const tool of manifest.tools) {
      expect(TOOL_ROUTES[tool.name], `route manquante pour ${tool.name}`).toBeDefined();
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
    const manifest = loadManifest();
    const resources = listResources(manifest.tools);
    expect(resources).toHaveLength(manifest.tools.length + 1);
    expect(resources[0]!.uri).toBe("leopardo://tools/catalog");
  });

  it("lit le catalogue et le schéma d'un outil", () => {
    const manifest = loadManifest();
    const catalog = readResource("leopardo://tools/catalog", manifest.tools);
    expect(catalog.contents[0]!.text).toContain("tools");

    const first = manifest.tools[0]!;
    const schema = readResource(`leopardo://tools/${first.name}/schema`, manifest.tools);
    expect(schema.contents[0]!.text).toContain(first.name);
  });

  it("rejette une ressource inconnue", () => {
    const manifest = loadManifest();
    expect(() => readResource("leopardo://tools/inexistant/schema", manifest.tools)).toThrow();
  });
});
