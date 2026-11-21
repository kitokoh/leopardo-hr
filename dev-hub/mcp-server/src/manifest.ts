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

export const ToolDefinitionSchema = z.object({
  name: z.string().regex(/^[a-z][a-z0-9_]{1,63}$/),
  description: z.string().min(1),
  inputSchema: z.record(z.string(), z.unknown()),
  outputSchema: z.record(z.string(), z.unknown()).default({}),
  permission: z.string().min(1),
  sensitivity: SensitivitySchema,
  bc: z.string().regex(/^BC-\d{2,3}$/),
  version: z.number().int().min(1),
});

export const ManifestSchema = z.object({
  schema_version: z.literal(1),
  generated_by: z.string(),
  tools: z.array(ToolDefinitionSchema),
});

export type ToolDefinition = z.infer<typeof ToolDefinitionSchema>;
export type Manifest = z.infer<typeof ManifestSchema>;

/** Chemin par défaut du manifeste, relatif à la racine du dépôt. */
export const DEFAULT_MANIFEST_PATH = resolve(
  dirname(fileURLToPath(import.meta.url)),
  "..",
  "manifest",
  "tools.json",
);

export function loadManifest(path: string = DEFAULT_MANIFEST_PATH): Manifest {
  const raw = readFileSync(path, "utf8");
  const parsed: unknown = JSON.parse(raw);
  return ManifestSchema.parse(parsed);
}
