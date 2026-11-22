import { readFileSync } from "node:fs";
import { dirname, resolve } from "node:path";
import { fileURLToPath } from "node:url";
import { z } from "zod";

/**
 * Manifeste d'outils produit par `php artisan ai:tools:manifest` (MCP-1, #9735).
 *
 * C'est la SOURCE UNIQUE DE VÉRITÉ du serveur : aucun nom d'outil n'est codé
 * en dur ici. Le serveur MCP découvre les outils exactement comme l'hôte BC-23
 * découvre les définitions via `AIToolDefinitionRegistry` (ADR-0019, option 3).
 */

const SensitivitySchema = z.enum(["read", "write", "send"]);

/**
 * Route REST portée par un outil GÉNÉRÉ depuis `api/openapi.yaml` (issue #9746).
 *
 * Les outils du contrat A3 n'en portent pas : leur correspondance REST vit dans
 * `TOOL_ROUTES` (`tools/execute.ts`), écrite à la main par le BC propriétaire.
 * Les outils générés, eux, portent leur route dans le manifeste — c'est ce qui
 * permet d'en produire 161 sans maintenir 161 entrées de table.
 */
export const ToolRouteSchema = z.object({
  method: z.enum(["GET", "POST", "PUT", "PATCH", "DELETE"]),
  path: z.string().startsWith("/api/v1/"),
  pathParams: z.array(z.string()).default([]),
  queryParams: z.array(z.string()).default([]),
  hasBody: z.boolean().default(false),
  /**
   * Endpoint d'ACTION métier (`/approve`, `/publish`, `/rotate`…) — issue #9753.
   *
   * Un outil d'action n'est JAMAIS exécuté directement : `tools/call` crée une
   * action en attente (`POST /api/v1/ai/actions/propose`) et renvoie au client
   * l'identifiant à confirmer par un humain. Le drapeau est porté par le
   * manifeste (donc par le générateur), jamais deviné à l'exécution.
   */
  action: z.boolean().default(false),
});

export const ToolDefinitionSchema = z.object({
  name: z.string().regex(/^[a-z][a-z0-9_]{1,63}$/),
  description: z.string().min(1),
  inputSchema: z.record(z.string(), z.unknown()),
  outputSchema: z.record(z.string(), z.unknown()).default({}),
  permission: z.string().min(1),
  sensitivity: SensitivitySchema,
  bc: z.string().regex(/^BC-\d{2,3}$/),
  version: z.number().int().min(1),
  route: ToolRouteSchema.optional(),
});

export const ManifestSchema = z.object({
  schema_version: z.literal(1),
  generated_by: z.string(),
  tools: z.array(ToolDefinitionSchema),
});

export type ToolDefinition = z.infer<typeof ToolDefinitionSchema>;
export type ToolRouteDefinition = z.infer<typeof ToolRouteSchema>;
export type Manifest = z.infer<typeof ManifestSchema>;

/** Chemin par défaut du manifeste A3, relatif à la racine du dépôt. */
export const DEFAULT_MANIFEST_PATH = resolve(
  dirname(fileURLToPath(import.meta.url)),
  "..",
  "manifest",
  "tools.json",
);

/**
 * Manifeste GÉNÉRÉ depuis `api/openapi.yaml` (issue #9746).
 *
 * Il est produit par `dev-hub/tools/generate-mcp-openapi-tools.py` et versionné :
 * le serveur ne lit jamais la spec OpenAPI à l'exécution (pas de parsing YAML au
 * démarrage, pas de dépendance runtime supplémentaire).
 */
export const DEFAULT_OPENAPI_MANIFEST_PATH = resolve(
  dirname(fileURLToPath(import.meta.url)),
  "..",
  "manifest",
  "openapi-tools.json",
);

export function loadManifest(path: string = DEFAULT_MANIFEST_PATH): Manifest {
  const raw = readFileSync(path, "utf8");
  const parsed: unknown = JSON.parse(raw);
  return ManifestSchema.parse(parsed);
}

/**
 * Charge les deux sources d'outils et les fusionne.
 *
 * Précédence : le contrat A3 gagne toujours. Un outil déclaré par un BC
 * propriétaire porte une sémantique métier (et une sensibilité) que le
 * générateur OpenAPI ne peut pas deviner — il ne doit jamais être écrasé par
 * une définition dérivée d'un verbe HTTP.
 */
export function loadAllTools(
  a3Path: string = DEFAULT_MANIFEST_PATH,
  openApiPath: string = DEFAULT_OPENAPI_MANIFEST_PATH,
): ToolDefinition[] {
  const a3 = loadManifest(a3Path).tools;
  const generated = loadManifest(openApiPath).tools;

  const byName = new Map<string, ToolDefinition>();
  for (const tool of generated) {
    byName.set(tool.name, tool);
  }
  for (const tool of a3) {
    byName.set(tool.name, tool);
  }

  return [...byName.values()].sort((a, b) => a.name.localeCompare(b.name));
}
