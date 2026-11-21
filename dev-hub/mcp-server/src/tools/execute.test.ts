import { describe, expect, it, vi } from "vitest";
import { TOOL_ROUTES, executeTool } from "./execute.js";
import { ApiClient } from "../api-client.js";
import { loadManifest } from "../manifest.js";

describe("TOOL_ROUTES", () => {
  it("mappe absence_decision vers l'endpoint REST canonique", () => {
    const route = TOOL_ROUTES.absence_decision;
    expect(route).toBeDefined();
    expect(route!.method).toBe("POST");
    expect(route!.path({ absence_id: 42, decision: "approve" })).toBe("/api/v1/absences/42/approve");
    expect(route!.path({ absence_id: 42, decision: "reject" })).toBe("/api/v1/absences/42/reject");
  });

  it("mappe shift_assign vers assign-employees", () => {
    const route = TOOL_ROUTES.shift_assign;
    expect(route!.path({ schedule_id: 7 })).toBe("/api/v1/schedules/7/assign-employees");
    expect(route!.body!({ employee_id: 3 })).toEqual({ employee_ids: [3] });
  });

  it("mappe notify_team vers announcements", () => {
    const route = TOOL_ROUTES.notify_team;
    expect(route!.path({})).toBe("/api/v1/announcements");
  });

  it("mappe les outils de lecture vers leurs endpoints", () => {
    expect(TOOL_ROUTES.team_overview!.path({})).toBe("/api/v1/employees");
    expect(TOOL_ROUTES.team_absences_recent!.path({})).toBe("/api/v1/absences");
    expect(TOOL_ROUTES.employee_leave_balance!.path({})).toBe("/api/v1/leave-balances");
    expect(TOOL_ROUTES.payroll_current_status!.path({})).toBe("/api/v1/payroll-runs");
  });
});

describe("executeTool", () => {
  const api = new ApiClient({ baseUrl: "https://api.test", token: "tok" });

  it("exécute un outil read via l'API", async () => {
    const fetchMock = vi.fn(async () => new Response(JSON.stringify({ data: [] }), { status: 200 }));
    vi.stubGlobal("fetch", fetchMock);

    const manifest = loadManifest();
    const tool = manifest.tools.find((t) => t.name === "team_overview")!;
    const result = await executeTool(tool, {}, api);

    expect(result.isError).toBeUndefined();
    expect(fetchMock).toHaveBeenCalledTimes(1);
    vi.unstubAllGlobals();
  });

  it("refuse d'exécuter un outil write sans confirmation (aucun appel API)", async () => {
    const fetchMock = vi.fn();
    vi.stubGlobal("fetch", fetchMock);

    const manifest = loadManifest();
    const tool = manifest.tools.find((t) => t.name === "absence_decision")!;
    const result = await executeTool(tool, { absence_id: 1, decision: "approve" }, api);

    expect(result.isError).toBe(true);
    expect(fetchMock).not.toHaveBeenCalled();
    vi.unstubAllGlobals();
  });

  it("refuse un outil sans route mappée (fail-closed)", async () => {
    const manifest = loadManifest();
    const tool = { ...manifest.tools[0]!, name: "outil_inconnu" };
    const result = await executeTool(tool, {}, api);
    expect(result.isError).toBe(true);
  });
});
