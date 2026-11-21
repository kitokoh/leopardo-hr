#!/usr/bin/env node
import { parseArgs } from "node:util";
import { loadAllTools } from "./manifest.js";
import { ApiClient } from "./api-client.js";
import { startStdio } from "./transports/stdio.js";
import { startHttp } from "./transports/http.js";

/**
 * Point d'entrée du serveur MCP Leopardo (MCP-2, #9736).
 *
 * Usage :
 *   leopardo-mcp --transport stdio --api-url=https://api.example.com --token=...
 *   leopardo-mcp --transport http  --port=8787 --api-url=... --token=...
 *
 * Le token est fourni par variable d'environnement (`LEOPARDO_API_TOKEN`) ou
 * par l'option `--token` — jamais committé.
 */

const { values } = parseArgs({
  options: {
    transport: { type: "string", default: "stdio" },
    port: { type: "string", default: "8787" },
    host: { type: "string", default: "127.0.0.1" },
    "api-url": { type: "string" },
    token: { type: "string" },
    manifest: { type: "string" },
  },
  allowPositionals: false,
});

const apiUrl = values["api-url"] ?? process.env.LEOPARDO_API_URL;
const token = values.token ?? process.env.LEOPARDO_API_TOKEN;

if (apiUrl === undefined || token === undefined) {
  process.stderr.write(
    "Configuration manquante : --api-url/LEOPARDO_API_URL et --token/LEOPARDO_API_TOKEN sont requis.\n",
  );
  process.exit(2);
}

// `--manifest` ne surcharge que le manifeste A3 ; le manifeste généré depuis
// OpenAPI (#9746) est toujours lu à côté de lui.
const tools = loadAllTools(values.manifest);
const api = new ApiClient({ baseUrl: apiUrl, token });

if (values.transport === "http") {
  const port = Number.parseInt(values.port, 10);
  await startHttp(tools, api, { port, host: values.host });
  process.stderr.write(`leopardo-mcp (http) à l'écoute sur http://${values.host}:${port}/mcp\n`);
} else {
  await startStdio(tools, api);
  process.stderr.write(`leopardo-mcp (stdio) prêt — ${tools.length} outils.\n`);
}
