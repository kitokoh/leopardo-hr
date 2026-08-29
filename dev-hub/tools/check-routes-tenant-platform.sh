#!/usr/bin/env bash
# check-routes-tenant-platform.sh — Garde routes/Policies platform vs tenant (MAT-003, issue #5861)
#
# Détecte statiquement les fuites d'autorité entre la surface plateforme
# (super-admin) et la surface tenant, à partir des fichiers de routes réels
# (api/routes/*.php + api/routes/modules/*.php) :
#
#   R1 — une route PLATFORM (auth:super_admin_api / auth:super_admin_web)
#        ne doit PAS activer le contexte tenant (middleware `tenant`).
#        Une route super-admin avec `tenant` = route platform exposée dans le
#        contexte tenant (ou un super-admin placé en contexte tenant).
#   R2 — une route TENANT (middleware `tenant`) ne doit pas référencer un
#        contrôleur strictement platform-admin (`PlatformAdmin*Controller`).
#   R3 — une route `auth:sanctum` sans middleware `tenant` ne doit pas
#        référencer un contrôleur de module tenant (HR, Payroll, Absence,
#        Attendance, Planning, Expense, Recruitment, CRM, Cabinet, Cameras,
#        Fleet, Notification, EdgeSync, Accounting, Growth, Billing,
#        Onboarding) — sauf allowlist explicite (webhooks/public/trial).
#
# Les cas où le platform-admin consomme légitimement un contrôleur de module
# (EdgeNodeController, configuration paie super-admin, …) sont HORS périmètre
# de cette garde : l'autorisation y est portée par `auth:super_admin_api` +
# Policies, pas par la classification des contrôleurs.
#
# Le parseur est statique (aucun artisan requis) : il suit la structure réelle
# `Route::middleware([...])->prefix('x')->group(fn)` avec groupes imbriqués et
# middleware de route. Les closures et routes générées par providers (Edge,
# SSO) ne sont pas couvertes (elles restent sous les gardes runtime existantes).
#
# Usage : bash dev-hub/tools/check-routes-tenant-platform.sh [repo_root]

set -euo pipefail

REPO_ROOT="${1:-}"
if [[ -z "$REPO_ROOT" ]]; then
  REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
fi
cd "$REPO_ROOT"

ROUTE_DIR="api/routes"
if [[ ! -d "$ROUTE_DIR" ]]; then
  echo "::error::Répertoire de routes introuvable : $ROUTE_DIR"
  exit 1
fi

python3 - "$REPO_ROOT" << 'PYEOF'
import re
import sys
from pathlib import Path

root = sys.argv[1]
route_dir = Path(root, "api/routes")

VERBS = "get|post|put|patch|delete|options|any|match"
TENANT_MODULES = {
    "HR", "Payroll", "Absence", "Attendance", "Planning", "Expense",
    "Recruitment", "CRM", "Cabinet", "Cameras", "Fleet", "Notification",
    "EdgeSync", "Accounting", "Growth", "Billing", "Onboarding",
}
PUBLIC_ALLOWLIST = {
    "PublicCareerController", "CandidateApplicationController",
    "PaymentWebhookController", "StripeWebhookController",
    "EmailBounceWebhookController", "AccountingPaymentWebhookController",
    "HealthController", "MetricsController",
    "LaunchReadinessController", "SupportedCountryController",
    "OnboardingChecklistController", "PublicHolidayController",
    "IslamicCalendarController", "SelfServiceTrialController",
    "DemoUserController", "ClientEventController",
    "CompanyRequestController", "MarketingLeadController",
    "TranslationCatalogController", "VoiceController",
}

def strip_comments(text):
    out, i, n, quote = [], 0, len(text), None
    while i < n:
        c = text[i]
        if quote:
            out.append(c)
            if c == "\\" and i + 1 < n:
                out.append(text[i + 1]); i += 2; continue
            if c == quote:
                quote = None
            i += 1; continue
        if c in "'\"":
            quote = c; out.append(c); i += 1; continue
        if c == "/" and i + 1 < n and text[i + 1] == "/":
            while i < n and text[i] != "\n":
                i += 1
            continue
        if c == "/" and i + 1 < n and text[i + 1] == "*":
            i += 2
            while i + 1 < n and not (text[i] == "*" and text[i + 1] == "/"):
                i += 1
            i += 2; continue
        out.append(c); i += 1
    return "".join(out)

def extract_middleware(chain):
    m = re.search(r"middleware\(\s*(\[[^\]]*\]|'[^']*')\s*\)", chain, re.S)
    if not m:
        return []
    raw = m.group(1)
    items = re.findall(r"'([^']+)'", raw) if raw.startswith("[") else [raw.strip("'")]
    normalized = []
    for it in items:
        it = it.strip()
        normalized.append(it if it.startswith("auth:") else it.split(":")[0])
    return normalized

def extract_prefix(chain):
    m = re.search(r"->prefix\(\s*'([^']+)'\s*\)", chain)
    return m.group(1) if m else ""

ROUTE_RE = re.compile(r"Route::(%s)\s*\(\s*'([^']+)'\s*,\s*([^)]*?)\s*\)" % VERBS, re.S)
GROUP_RE = re.compile(
    r"Route::middleware\(\s*(\[[^\]]*\]|'[^']*')\s*\)"
    r"(?:(?:->prefix\(\s*'([^']+)'\s*\)|->name\(\s*'[^']+'\s*\)))*"
    r"->group\s*\(\s*(?:function\s*\(\s*\)\s*(?::\s*void)?\s*)?\{",
    re.S,
)
PREFIX_GROUP_RE = re.compile(
    r"Route::prefix\(\s*'([^']+)'\s*\)->group\s*\(\s*(?:function\s*\(\s*\)\s*(?::\s*void)?\s*)?\{",
    re.S,
)

def find_balanced(text, start):
    depth = 0
    for i in range(start, len(text)):
        if text[i] == "{":
            depth += 1
        elif text[i] == "}":
            depth -= 1
            if depth == 0:
                return i
    return len(text)

def parse_file(text):
    routes = []
    text = strip_comments(text)

    def process(segment, ctx_mw, ctx_prefix):
        pos = 0
        while True:
            g = GROUP_RE.search(segment, pos)
            pg = PREFIX_GROUP_RE.search(segment, pos)
            r = ROUTE_RE.search(segment, pos)
            group_starts = [m.start() for m in (g, pg) if m]
            candidates = []
            if group_starts:
                candidates.append((min(group_starts), "group"))
            if r:
                candidates.append((r.start(), "route"))
            if not candidates:
                break
            candidates.sort(key=lambda c: c[0])
            if candidates[0][1] == "route":
                chain_after = segment[r.end():r.end() + 400]
                route_mw = extract_middleware(chain_after.split(";")[0])
                routes.append({
                    "uri": r.group(2),
                    "middleware": ctx_mw + route_mw,
                    "action": r.group(3).strip(),
                })
                pos = r.end()
                continue
            if g and pg:
                nxt = g if g.start() <= pg.start() else pg
            else:
                nxt = g or pg
            if nxt is g:
                mw = ctx_mw + extract_middleware(segment[g.start():g.end()])
                prefix = ctx_prefix + extract_prefix(segment[g.start():g.end()])
                body_start = g.end()
            else:
                mw = ctx_mw
                prefix = ctx_prefix + pg.group(1)
                body_start = pg.end()
            close = find_balanced(segment, body_start - 1)
            process(segment[body_start:close], mw, prefix)
            pos = close + 1

    process(text, [], "")
    return routes

def resolve_action(action, uses):
    action = action.strip()
    if action.startswith("fn") or action.startswith("function"):
        return None
    m = re.match(r"\[?\s*([A-Za-z_\\][A-Za-z0-9_\\]*)::class\s*,?\s*(?:'([^']+)')?\s*\]?", action)
    if m:
        return uses.get(m.group(1), m.group(1))
    m = re.match(r"'([^']+)'", action)
    if m:
        ctrl = m.group(1)
        cls = ctrl.split("@", 1)[0] if "@" in ctrl else ctrl
        return uses.get(cls, cls)
    return None

# ── Analyse ──────────────────────────────────────────────────────────────────
files = sorted(route_dir.glob("*.php")) + sorted((route_dir / "modules").glob("*.php"))
violations = []
total = 0
for f in files:
    text = f.read_text(encoding="utf-8", errors="replace")
    uses = {}
    for full in re.findall(r"^use\s+([A-Za-z0-9_\\]+);", text, re.M):
        uses.setdefault(full.rsplit("\\", 1)[-1], full)
    for route in parse_file(text):
        total += 1
        fqcn = resolve_action(route["action"], uses)
        name = (fqcn or "").rsplit("\\", 1)[-1]
        mw = route["middleware"]
        rel = str(f.relative_to(root))

        is_platform = "auth:super_admin_api" in mw or "auth:super_admin_web" in mw
        is_tenant_scope = "tenant" in mw
        has_sanctum = "auth:sanctum" in mw

        # R1 — route platform avec contexte tenant
        if is_platform and is_tenant_scope:
            violations.append((f"R1 — route {is_platform and 'platform'} avec middleware tenant "
                               f"(surface super-admin exposée en contexte tenant)", rel, route, fqcn))
            continue
        # R2 — route tenant vers contrôleur strictement platform-admin
        if is_tenant_scope and fqcn and name.startswith("PlatformAdmin") and name not in PUBLIC_ALLOWLIST:
            violations.append((f"R2 — route tenant référençant le contrôleur platform-admin {fqcn}", rel, route, fqcn))
            continue
        # R3 — sanctum sans tenant sur contrôleur de module tenant
        if has_sanctum and not is_tenant_scope and not is_platform:
            if fqcn:
                m = re.match(r"App\\Modules\\([A-Za-z0-9_]+)\\", fqcn)
                if m and m.group(1) in TENANT_MODULES and name not in PUBLIC_ALLOWLIST:
                    violations.append((f"R3 — contrôleur de module tenant {fqcn} sans middleware tenant "
                                       f"(isolation cross-tenant)", rel, route, fqcn))

if violations:
    print("")
    print("══════════════════════════════════════════════════════════════")
    print("  ROUTES TENANT/PLATFORM GUARD — MAT-003 (issue #5861)")
    print("══════════════════════════════════════════════════════════════")
    print(f"  {len(violations)} violation(s) de séparation tenant/platform :")
    print("")
    for msg, rel, route, fqcn in violations:
        print(f"  ❌  {msg}")
        print(f"      {rel} | {route['uri']} | middleware={route['middleware']}")
    print("")
    print("  Fix : aligner le middleware de la route sur sa surface")
    print("  (tenant → 'auth:sanctum'+'tenant' ; platform → 'auth:super_admin_*')")
    print("  ou corriger le contrôleur référencé. Une exception légitime passe")
    print("  par l'allowlist documentée dans le script (jamais par silence).")
    print("")
    sys.exit(1)

print(f"✅  Routes tenant/platform OK — {total} routes analysées statiquement, "
      f"aucune fuite d'autorité détectée (R1/R2/R3).")
PYEOF
