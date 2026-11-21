import { z } from "zod";

/**
 * Client HTTP vers l'API Laravel (MCP-3, #9737).
 *
 * Le serveur MCP ne décide JAMAIS d'une autorisation : il relaie le token du
 * client et laisse Laravel appliquer tenant + RBAC + Policy du BC propriétaire
 * (spec §3 « jamais de confiance au LLM »). Aucun accès base de données.
 */

export interface ApiClientOptions {
  baseUrl: string;
  token: string;
  timeoutMs?: number;
}

export class ApiError extends Error {
  constructor(
    message: string,
    readonly status: number,
    readonly body: unknown,
  ) {
    super(message);
    this.name = "ApiError";
  }
}

export interface ApiRequest {
  method: "GET" | "POST" | "PUT" | "PATCH" | "DELETE";
  path: string;
  query?: Record<string, string | number | boolean | undefined>;
  body?: unknown;
}

export class ApiClient {
  private readonly baseUrl: string;
  private readonly token: string;
  private readonly timeoutMs: number;

  constructor(options: ApiClientOptions) {
    this.baseUrl = options.baseUrl.replace(/\/+$/, "");
    this.token = options.token;
    this.timeoutMs = options.timeoutMs ?? 30_000;
  }

  async request<T = unknown>(request: ApiRequest): Promise<T> {
    const url = new URL(`${this.baseUrl}${request.path}`);
    for (const [key, value] of Object.entries(request.query ?? {})) {
      if (value !== undefined) {
        url.searchParams.set(key, String(value));
      }
    }

    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), this.timeoutMs);

    try {
      const response = await fetch(url, {
        method: request.method,
        headers: {
          accept: "application/json",
          "content-type": "application/json",
          authorization: `Bearer ${this.token}`,
        },
        body: request.body === undefined ? undefined : JSON.stringify(request.body),
        signal: controller.signal,
      });

      const text = await response.text();
      const parsed: unknown = text === "" ? null : safeJson(text);

      if (!response.ok) {
        throw new ApiError(
          `API ${request.method} ${request.path} → ${response.status}`,
          response.status,
          parsed,
        );
      }

      return parsed as T;
    } finally {
      clearTimeout(timer);
    }
  }
}

function safeJson(text: string): unknown {
  try {
    return JSON.parse(text);
  } catch {
    return text;
  }
}

/** Schéma de configuration du client (validation au démarrage). */
export const ApiClientOptionsSchema = z.object({
  baseUrl: z.string().url(),
  token: z.string().min(1),
  timeoutMs: z.number().int().positive().optional(),
});
