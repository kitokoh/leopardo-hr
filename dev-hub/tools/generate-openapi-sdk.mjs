#!/usr/bin/env node
import { createHash } from "node:crypto";
import { existsSync, mkdirSync, readFileSync, writeFileSync } from "node:fs";
import { dirname, join, resolve } from "node:path";
import { fileURLToPath } from "node:url";

const rootDir = resolve(dirname(fileURLToPath(import.meta.url)), "..", "..");
const specPath = join(rootDir, "api", "openapi.yaml");
const sdkDir = join(rootDir, "dev-hub", "sdk");
const checkOnly = process.argv.includes("--check");

const httpMethods = new Set(["get", "post", "put", "patch", "delete", "head", "options"]);

function words(value) {
  return String(value)
    .replace(/[{}]/g, "")
    .replace(/[^A-Za-z0-9]+/g, " ")
    .trim()
    .split(/\s+/)
    .filter(Boolean);
}

function pascal(value) {
  return words(value)
    .map((word) => `${word.charAt(0).toUpperCase()}${word.slice(1)}`)
    .join("");
}

function camel(value) {
  const valuePascal = pascal(value);
  return `${valuePascal.charAt(0).toLowerCase()}${valuePascal.slice(1)}`;
}

function snake(value) {
  const result = words(value)
    .map((word) => word.toLowerCase())
    .join("_");

  return /^[0-9]/.test(result) ? `op_${result}` : result;
}

function fallbackOperationId(method, path) {
  const suffix = path
    .split("/")
    .filter(Boolean)
    .map((segment) => (segment.startsWith("{") ? `by_${segment.slice(1, -1)}` : segment))
    .join("_");

  return `${method}_${suffix || "root"}`;
}

function parseQuotedYamlValue(value) {
  const trimmed = value.trim();

  if ((trimmed.startsWith('"') && trimmed.endsWith('"')) || (trimmed.startsWith("'") && trimmed.endsWith("'"))) {
    return trimmed.slice(1, -1);
  }

  return trimmed;
}

function readOpenApiSummary() {
  const source = readFileSync(specPath, "utf8");
  const lines = source.split(/\r?\n/);
  const summary = {
    openapi: "unknown",
    info: {
      version: "unknown",
    },
    operations: [],
  };

  let inInfo = false;
  let inPaths = false;
  let currentPath = null;
  let currentOperation = null;

  function flushOperation() {
    if (currentOperation) {
      summary.operations.push(currentOperation);
      currentOperation = null;
    }
  }

  for (const line of lines) {
    const openApiMatch = line.match(/^openapi:\s*(.+)$/);
    if (openApiMatch) {
      summary.openapi = parseQuotedYamlValue(openApiMatch[1]);
      continue;
    }

    if (line === "info:") {
      inInfo = true;
      continue;
    }

    if (/^[A-Za-z0-9_-]+:/.test(line) && !line.startsWith("info:")) {
      inInfo = false;
    }

    if (inInfo) {
      const versionMatch = line.match(/^  version:\s*(.+)$/);
      if (versionMatch) {
        summary.info.version = parseQuotedYamlValue(versionMatch[1]);
      }
    }

    if (line === "paths:") {
      inPaths = true;
      continue;
    }

    if (!inPaths) {
      continue;
    }

    const pathMatch = line.match(/^  (\/[^:]+):\s*$/);
    if (pathMatch) {
      flushOperation();
      currentPath = pathMatch[1];
      continue;
    }

    const methodMatch = line.match(/^    (get|post|put|patch|delete|head|options):\s*$/);
    if (methodMatch && currentPath) {
      flushOperation();
      currentOperation = {
        method: methodMatch[1].toUpperCase(),
        path: currentPath,
        rawId: fallbackOperationId(methodMatch[1], currentPath),
        summary: "",
      };
      continue;
    }

    if (!currentOperation) {
      continue;
    }

    const operationIdMatch = line.match(/^      operationId:\s*(.+)$/);
    if (operationIdMatch) {
      currentOperation.rawId = parseQuotedYamlValue(operationIdMatch[1]);
      continue;
    }

    const summaryMatch = line.match(/^      summary:\s*(.+)$/);
    if (summaryMatch) {
      currentOperation.summary = parseQuotedYamlValue(summaryMatch[1]);
    }
  }

  flushOperation();

  return summary;
}

function collectOperations(openapi) {
  const taken = new Map();
  const operations = [];

  for (const operation of openapi.operations) {
    const baseJsName = camel(operation.rawId);
    const basePyName = snake(operation.rawId);
    const duplicate = taken.get(baseJsName) ?? 0;
    taken.set(baseJsName, duplicate + 1);

    operations.push({
      method: operation.method,
      path: operation.path,
      jsName: duplicate === 0 ? baseJsName : `${baseJsName}${duplicate + 1}`,
      pyName: duplicate === 0 ? basePyName : `${basePyName}_${duplicate + 1}`,
      summary: operation.summary ?? "",
    });
  }

  return operations.sort((left, right) => `${left.path} ${left.method}`.localeCompare(`${right.path} ${right.method}`));
}

function jsString(value) {
  return JSON.stringify(value);
}

function pythonString(value) {
  return JSON.stringify(value);
}

function generateJavaScript(openapi, operations) {
  const methods = operations
    .map((operation) => {
      const summary = operation.summary ? `\n    /** ${operation.summary.replace(/\*\//g, "* /")} */` : "";
      return `${summary}
    ${operation.jsName}(options = {}) {
      return request(${jsString(operation.method)}, ${jsString(operation.path)}, options);
    }`;
    })
    .join(",\n");

  return `// Generated by dev-hub/tools/generate-openapi-sdk.mjs from api/openapi.yaml.
// Do not edit manually.

export class LeopardoApiError extends Error {
  constructor(message, { status, response, body }) {
    super(message);
    this.name = "LeopardoApiError";
    this.status = status;
    this.response = response;
    this.body = body;
  }
}

export function createLeopardoClient({ baseUrl, token, fetchImpl = globalThis.fetch } = {}) {
  if (!baseUrl) {
    throw new TypeError("baseUrl is required");
  }

  if (typeof fetchImpl !== "function") {
    throw new TypeError("fetchImpl is required when global fetch is unavailable");
  }

  const normalizedBaseUrl = baseUrl.replace(/\\/+$/, "");

  function buildPath(pathTemplate, pathParams = {}) {
    return pathTemplate.replace(/\\{([^}]+)\\}/g, (_, name) => {
      if (pathParams[name] === undefined || pathParams[name] === null) {
        throw new TypeError(\`Missing path parameter: \${name}\`);
      }

      return encodeURIComponent(String(pathParams[name]));
    });
  }

  function buildUrl(pathTemplate, { pathParams, query } = {}) {
    const url = new URL(\`\${normalizedBaseUrl}\${buildPath(pathTemplate, pathParams)}\`);

    for (const [key, value] of Object.entries(query ?? {})) {
      if (value === undefined || value === null) {
        continue;
      }

      if (Array.isArray(value)) {
        for (const item of value) {
          url.searchParams.append(key, String(item));
        }
      } else {
        url.searchParams.set(key, String(value));
      }
    }

    return url;
  }

  async function request(method, pathTemplate, { pathParams, query, body, headers = {} } = {}) {
    const requestHeaders = {
      Accept: "application/json",
      ...headers,
    };

    if (token) {
      requestHeaders.Authorization = \`Bearer \${token}\`;
    }

    const init = {
      method,
      headers: requestHeaders,
    };

    if (body !== undefined) {
      requestHeaders["Content-Type"] = requestHeaders["Content-Type"] ?? "application/json";
      init.body = requestHeaders["Content-Type"].includes("application/json") ? JSON.stringify(body) : body;
    }

    const response = await fetchImpl(buildUrl(pathTemplate, { pathParams, query }), init);
    const contentType = response.headers.get("content-type") ?? "";
    const payload = contentType.includes("application/json") ? await response.json() : await response.text();

    if (!response.ok) {
      throw new LeopardoApiError(\`Leopardo API request failed with status \${response.status}\`, {
        status: response.status,
        response,
        body: payload,
      });
    }

    return payload;
  }

  return {
    request,${methods ? `\n${methods}` : ""}
  };
}

export default createLeopardoClient;

export const openApiVersion = ${jsString(openapi.info?.version ?? "unknown")};
`;
}

function generatePython(openapi, operations) {
  const methods = operations
    .map((operation) => {
      const docstring = operation.summary ? `\n        \"\"\"${operation.summary.replace(/"""/g, '\\"\\"\\"')}\"\"\"` : "";
      return `    def ${operation.pyName}(self, **kwargs):${docstring}
        return self.request(${pythonString(operation.method)}, ${pythonString(operation.path)}, **kwargs)`;
    })
    .join("\n\n");

  return `# Generated by dev-hub/tools/generate-openapi-sdk.mjs from api/openapi.yaml.
# Do not edit manually.

import json
from urllib import error, parse, request as urllib_request


open_api_version = ${pythonString(openapi.info?.version ?? "unknown")}


class LeopardoApiError(Exception):
    def __init__(self, message, *, status, body):
        super().__init__(message)
        self.status = status
        self.body = body


class LeopardoClient:
    def __init__(self, *, base_url, token=None, timeout=30):
        if not base_url:
            raise ValueError("base_url is required")

        self.base_url = base_url.rstrip("/")
        self.token = token
        self.timeout = timeout

    def _build_path(self, path_template, path_params=None):
        path_params = path_params or {}
        rendered = path_template

        for key, value in path_params.items():
            rendered = rendered.replace("{" + key + "}", parse.quote(str(value), safe=""))

        if "{" in rendered or "}" in rendered:
            raise ValueError("Missing path parameter in " + path_template)

        return rendered

    def _build_url(self, path_template, *, path_params=None, query=None):
        url = self.base_url + self._build_path(path_template, path_params)
        clean_query = {}

        for key, value in (query or {}).items():
            if value is None:
                continue
            clean_query[key] = value

        if clean_query:
            url += "?" + parse.urlencode(clean_query, doseq=True)

        return url

    def request(self, method, path_template, *, path_params=None, query=None, body=None, headers=None):
        request_headers = {
            "Accept": "application/json",
            **(headers or {}),
        }

        if self.token:
            request_headers["Authorization"] = "Bearer " + self.token

        data = None
        if body is not None:
            request_headers.setdefault("Content-Type", "application/json")
            data = json.dumps(body).encode("utf-8")

        req = urllib_request.Request(
            self._build_url(path_template, path_params=path_params, query=query),
            data=data,
            headers=request_headers,
            method=method,
        )

        try:
            with urllib_request.urlopen(req, timeout=self.timeout) as response:
                raw = response.read()
                content_type = response.headers.get("content-type", "")
                if "application/json" in content_type:
                    return json.loads(raw.decode("utf-8")) if raw else None
                return raw.decode("utf-8")
        except error.HTTPError as exc:
            raw = exc.read()
            body_text = raw.decode("utf-8") if raw else ""
            try:
                parsed_body = json.loads(body_text) if body_text else None
            except json.JSONDecodeError:
                parsed_body = body_text

            raise LeopardoApiError(
                "Leopardo API request failed with status " + str(exc.code),
                status=exc.code,
                body=parsed_body,
            ) from exc

${methods}
`;
}

function generateManifest(openapi, operations) {
  const source = readFileSync(specPath, "utf8");

  return `${JSON.stringify(
    {
      source: "api/openapi.yaml",
      spec_sha256: createHash("sha256").update(source).digest("hex"),
      openapi: openapi.openapi,
      api_version: openapi.info?.version ?? "unknown",
      operation_count: operations.length,
      targets: ["javascript", "python"],
    },
    null,
    2,
  )}\n`;
}

function writeOrCheck(relativePath, content) {
  const fullPath = join(rootDir, relativePath);

  if (checkOnly) {
    if (!existsSync(fullPath)) {
      throw new Error(`${relativePath} is missing. Run node dev-hub/tools/generate-openapi-sdk.mjs.`);
    }

    const current = readFileSync(fullPath, "utf8");
    if (current !== content) {
      throw new Error(`${relativePath} is not up to date. Run node dev-hub/tools/generate-openapi-sdk.mjs.`);
    }

    return;
  }

  mkdirSync(dirname(fullPath), { recursive: true });
  writeFileSync(fullPath, content, "utf8");
}

const openapi = readOpenApiSummary();
const operations = collectOperations(openapi);

writeOrCheck("dev-hub/sdk/javascript/leopardoClient.js", generateJavaScript(openapi, operations));
writeOrCheck("dev-hub/sdk/python/leopardo_client.py", generatePython(openapi, operations));
writeOrCheck("dev-hub/sdk/MANIFEST.json", generateManifest(openapi, operations));

console.log(`${checkOnly ? "Checked" : "Generated"} ${operations.length} OpenAPI operations for JavaScript and Python SDKs.`);
