import { Server } from "@modelcontextprotocol/sdk/server/index.js";
import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";
import type { ToolDefinition } from "../manifest.js";
import type { ApiClient } from "../api-client.js";
import { buildServer } from "../server.js";

/** Transport stdio — clients MCP locaux (IDE, CLI). */
export async function startStdio(tools: ToolDefinition[], api: ApiClient): Promise<Server> {
  const server = buildServer(tools, api);
  const transport = new StdioServerTransport();
  await server.connect(transport);
  return server;
}
