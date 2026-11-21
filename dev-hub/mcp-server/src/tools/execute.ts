import type { CallToolResult } from "@modelcontextprotocol/sdk/types.js";
import type { ToolDefinition } from "../manifest.js";
import type { ApiClient } from "../api-client.js";
import { ApiError } from "../api-client.js";

/**
 * Pont d'exécution `tools/call` → API REST `/api/v1` (MCP-3, #9737).
 *
 * Règle de sensibilité (déclarée par le BC propriétaire, jamais décidée ici) :
 *   - `read`        → exécution directe (après RBAC côté Laravel) ;
 *   - `write|send`  → AUCUN effet de bord : le serveur MCP refuse d'exécuter
 *                     directement et renvoie la marche à suivre (confirmation
 *                     humaine via le flux PendingAction de Laravel).
 *
 * Le mapping outil → endpoint REST est EXPLICITE et testé : il ne devine rien.
 */

export interface ToolRoute {
  method: "GET" | "POST" | "PUT" | "PATCH" | "DELETE";
  path: (args: Record<string, unknown>) => string;
  query?: (args: Record<string, unknown>) => Record<string, string | number | boolean | undefined>;
  body?: (args: Record<string, unknown>) => unknown;
}

/**
 * Table de correspondance outil → endpoint REST canonique.
 *
 * Les chemins sont ceux inventoriés dans `api/openapi.yaml` et
 * `api/routes/modules/*.php` (parité avec les endpoints REST existants).
 */
export const TOOL_ROUTES: Record<string, ToolRoute> = {
  team_overview: {
    method: "GET",
    path: () => "/api/v1/employees",
    query: (args) => ({ department_id: asNumber(args.department_id) }),
  },
  team_absences_recent: {
    method: "GET",
    path: () => "/api/v1/absences",
    query: (args) => ({ status: asString(args.status) }),
  },
  employee_leave_balance: {
    method: "GET",
    path: () => "/api/v1/leave-balances",
    query: (args) => ({ employee_id: asNumber(args.employee_id) }),
  },
  payroll_current_status: {
    method: "GET",
    path: () => "/api/v1/payroll-runs",
  },
  absence_decision: {
    method: "POST",
    path: (args) =>
      `/api/v1/absences/${String(args.absence_id)}/${args.decision === "reject" ? "reject" : "approve"}`,
    body: (args) => (args.reason === undefined ? {} : { reason: args.reason }),
  },
  shift_assign: {
    method: "POST",
    path: (args) => `/api/v1/schedules/${String(args.schedule_id)}/assign-employees`,
    body: (args) => ({ employee_ids: [asNumber(args.employee_id)] }),
  },
  notify_team: {
    method: "POST",
    path: () => "/api/v1/announcements",
    body: (args) => ({
      title: args.title,
      message: args.message,
      audience_type: args.audience_type ?? "department",
      department_id: asNumber(args.department_id),
    }),
  },
  email_classify: {
    method: "POST",
    path: (args) => `/api/v1/communication/messages/${String(args.message_id)}/classify`,
    body: (args) => (args.force === undefined ? {} : { force: args.force }),
  },
  email_reply_draft: {
    method: "POST",
    path: (args) => `/api/v1/communication/messages/${String(args.message_id)}/reply-draft`,
    body: () => ({}),
  },
};

export async function executeTool(
  tool: ToolDefinition,
  args: Record<string, unknown>,
  api: ApiClient,
): Promise<CallToolResult> {
  const route = TOOL_ROUTES[tool.name];

  if (route === undefined) {
    return errorResult(
      `Outil '${tool.name}' déclaré (${tool.bc}) mais sans route REST mappée — exécution refusée (fail-closed).`,
    );
  }

  if (tool.sensitivity !== "read") {
    return errorResult(
      `Outil '${tool.name}' de sensibilité '${tool.sensitivity}' : exécution directe interdite. ` +
        `Confirmer l'action via le flux PendingAction de l'API (POST /api/v1/ai/actions/{id}/confirm).`,
    );
  }

  try {
    const payload = await api.request({
      method: route.method,
      path: route.path(args),
      ...(route.query === undefined ? {} : { query: route.query(args) }),
      ...(route.body === undefined ? {} : { body: route.body(args) }),
    });

    return { content: [{ type: "text", text: JSON.stringify(payload) }] };
  } catch (error) {
    if (error instanceof ApiError) {
      return errorResult(`Refus ou erreur API (${error.status}) : ${JSON.stringify(error.body)}`);
    }
    return errorResult(`Erreur d'exécution : ${(error as Error).message}`);
  }
}

function errorResult(message: string): CallToolResult {
  return { isError: true, content: [{ type: "text", text: message }] };
}

function asNumber(value: unknown): number | undefined {
  return typeof value === "number" ? value : undefined;
}

function asString(value: unknown): string | undefined {
  return typeof value === "string" ? value : undefined;
}
