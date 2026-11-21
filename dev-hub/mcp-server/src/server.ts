import { Server } from "@modelcontextprotocol/sdk/server/index.js";
import {
  CallToolRequestSchema,
  ListResourcesRequestSchema,
  ListToolsRequestSchema,
  ReadResourceRequestSchema,
} from "@modelcontextprotocol/sdk/types.js";
import type { CallToolResult, ReadResourceResult } from "@modelcontextprotocol/sdk/types.js";
import type { ToolDefinition } from "./manifest.js";
import { executeTool } from "./tools/execute.js";
import { readResource, listResources } from "./resources.js";
import type { ApiClient } from "./api-client.js";

/**
 * Construit le serveur MCP à partir du manifeste d'outils (MCP-2, #9736).
 *
 * On utilise l'API BAS NIVEAU (`Server` + handlers JSON-RPC) et non
 * `McpServer.registerTool` : le manifeste porte des schémas **JSON Schema**
 * (produits par `AIToolDefinition`, contrat A3) et non des schémas Zod — les
 * passer tels quels préserve le contrat déclaré par les BC sans le réécrire.
 *
 * `tools/list` renvoie EXACTEMENT les outils du manifeste : le serveur ne
 * connaît aucun nom d'outil en dur, il ne fait que relayer le contrat déclaré
 * par les BC propriétaires (AIToolDefinition, #6850).
 */
export function buildServer(tools: ToolDefinition[], api: ApiClient): Server {
  const server = new Server(
    { name: "leopardo-mcp", version: "0.1.0" },
    { capabilities: { tools: {}, resources: {} } },
  );

  server.setRequestHandler(ListToolsRequestSchema, async () => ({
    tools: tools.map((tool) => ({
      name: tool.name,
      description: tool.description,
      inputSchema: tool.inputSchema as { type: "object"; [key: string]: unknown },
    })),
  }));

  server.setRequestHandler(CallToolRequestSchema, async (request): Promise<CallToolResult> => {
    const tool = tools.find((candidate) => candidate.name === request.params.name);
    if (tool === undefined) {
      return {
        isError: true,
        content: [{ type: "text", text: `Outil inconnu : '${request.params.name}' (fail-closed).` }],
      };
    }

    return executeTool(tool, (request.params.arguments ?? {}) as Record<string, unknown>, api);
  });

  server.setRequestHandler(ListResourcesRequestSchema, async () => ({ resources: listResources(tools) }));

  server.setRequestHandler(
    ReadResourceRequestSchema,
    async (request): Promise<ReadResourceResult> => readResource(request.params.uri, tools),
  );

  return server;
}
