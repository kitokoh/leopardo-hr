import { createServer, type IncomingMessage, type ServerResponse } from "node:http";
import { StreamableHTTPServerTransport } from "@modelcontextprotocol/sdk/server/streamableHttp.js";
import type { ToolDefinition } from "../manifest.js";
import type { ApiClient } from "../api-client.js";
import { buildServer } from "../server.js";

/**
 * Transport Streamable HTTP (MCP-2, #9736) — clients MCP distants.
 *
 * Endpoints :
 *   POST /mcp     — requêtes JSON-RPC MCP (stateless : un serveur par requête)
 *   GET  /healthz — sonde de disponibilité (aucune donnée métier)
 */
export interface HttpOptions {
  port: number;
  host: string;
}

export async function startHttp(
  tools: ToolDefinition[],
  api: ApiClient,
  options: HttpOptions,
): Promise<{ close: () => Promise<void> }> {
  const httpServer = createServer((req: IncomingMessage, res: ServerResponse) => {
    void handle(req, res, tools, api);
  });

  await new Promise<void>((resolve) => {
    httpServer.listen(options.port, options.host, resolve);
  });

  return {
    close: () =>
      new Promise<void>((resolve, reject) => {
        httpServer.close((err) => (err ? reject(err) : resolve()));
      }),
  };
}

async function handle(
  req: IncomingMessage,
  res: ServerResponse,
  tools: ToolDefinition[],
  api: ApiClient,
): Promise<void> {
  const url = new URL(req.url ?? "/", `http://${req.headers.host ?? "localhost"}`);

  if (url.pathname === "/healthz") {
    res.writeHead(200, { "content-type": "application/json" });
    res.end(JSON.stringify({ status: "ok", tools: tools.length }));
    return;
  }

  if (url.pathname !== "/mcp") {
    res.writeHead(404, { "content-type": "application/json" });
    res.end(JSON.stringify({ error: "not_found" }));
    return;
  }

  // Stateless : un serveur + un transport par requête (pas de session à
  // maintenir côté serveur MCP — l'état métier vit dans Laravel).
  const server = buildServer(tools, api);
  const transport = new StreamableHTTPServerTransport({ sessionIdGenerator: undefined });

  res.on("close", () => {
    void transport.close();
    void server.close();
  });

  await server.connect(transport);
  await transport.handleRequest(req, res);
}
