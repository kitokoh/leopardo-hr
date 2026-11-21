import type { ReadResourceResult } from "@modelcontextprotocol/sdk/types.js";
import type { ToolDefinition } from "./manifest.js";

/**
 * Ressources MCP (MCP-4, #9738) — lecture seule, aucune donnée métier.
 *
 *   leopardo://tools/catalog        — le manifeste complet (JSON)
 *   leopardo://tools/{name}/schema  — schémas d'entrée/sortie d'un outil
 */

export interface ResourceDescriptor {
  uri: string;
  name: string;
  title: string;
  description: string;
  mimeType: string;
}

export function listResources(tools: ToolDefinition[]): ResourceDescriptor[] {
  return [
    {
      uri: "leopardo://tools/catalog",
      name: "tools-catalog",
      title: "Catalogue d'outils Leopardo",
      description: "Manifeste des outils déclarés par les bounded contexts (contrat A3).",
      mimeType: "application/json",
    },
    ...tools.map((tool) => ({
      uri: `leopardo://tools/${tool.name}/schema`,
      name: `tool-schema-${tool.name}`,
      title: `Schémas — ${tool.name}`,
      description: `Schémas d'entrée/sortie de l'outil ${tool.name} (${tool.bc}).`,
      mimeType: "application/json",
    })),
  ];
}

export function readResource(uri: string, tools: ToolDefinition[]): ReadResourceResult {
  if (uri === "leopardo://tools/catalog") {
    return {
      contents: [
        { uri, mimeType: "application/json", text: JSON.stringify({ tools }, null, 2) },
      ],
    };
  }

  const match = /^leopardo:\/\/tools\/([a-z0-9_]+)\/schema$/.exec(uri);
  const tool = match === null ? undefined : tools.find((candidate) => candidate.name === match[1]);

  if (tool === undefined) {
    throw new Error(`Ressource inconnue : ${uri}`);
  }

  return {
    contents: [
      {
        uri,
        mimeType: "application/json",
        text: JSON.stringify(
          {
            name: tool.name,
            bc: tool.bc,
            permission: tool.permission,
            sensitivity: tool.sensitivity,
            inputSchema: tool.inputSchema,
            outputSchema: tool.outputSchema,
          },
          null,
          2,
        ),
      },
    ],
  };
}
