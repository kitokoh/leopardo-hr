#!/usr/bin/env bash
# Smoke post-deploy API verification
# Usage: ./smoke-post-deploy.sh [API_BASE_URL]
# Default: https://gestionemployerbackend.onrender.com

set -euo pipefail

API_BASE="${1:-https://gestionemployerbackend.onrender.com}"
PASS=0
FAIL=0
SKIP=0
RESULTS=()

check() {
  local name="$1"
  local method="${2:-GET}"
  local path="$3"
  local expected_status="${4:-200}"
  local body="${5:-}"
  local extra_header="${6:-}"

  local url="${API_BASE}${path}"
  local args=(--silent --show-error --max-time 15 --write-out "%{http_code}" --output /tmp/smoke-body.txt -H "Accept: application/json")

  if [[ -n "$extra_header" ]]; then
    args+=(-H "$extra_header")
  fi

  if [[ "$method" == "POST" ]]; then
    args+=(-X POST -H "Content-Type: application/json")
    if [[ -n "$body" ]]; then
      args+=(-d "$body")
    fi
  fi

  local status
  status=$(curl "${args[@]}" "$url" 2>/dev/null) || status="000"

  if [[ "$status" == "$expected_status" ]]; then
    echo "[PASS] $name (${method} ${path} → ${status})"
    PASS=$((PASS + 1))
    RESULTS+=("PASS|$name|$method $path|$status")
  else
    echo "[FAIL] $name (${method} ${path} → ${status}, expected ${expected_status})"
    FAIL=$((FAIL + 1))
    RESULTS+=("FAIL|$name|$method $path|$status (expected $expected_status)")
  fi
}

echo "============================================"
echo "  Leopardo RH — Smoke Post-Deploy"
echo "  Target: ${API_BASE}"
echo "  Date:   $(date -u +%Y-%m-%dT%H:%M:%SZ)"
echo "============================================"
echo ""

# 1. Health endpoints
check "Health live"           GET  "/api/v1/health/live"   200
check "Health ready"          GET  "/api/v1/health/ready"  200
check "Health general"        GET  "/api/v1/health"        200

# 2. Auth endpoints
check "Auth login (no creds)" POST "/api/v1/auth/login"   422 '{"email":"","password":""}'
check "Auth me (no token)"    GET  "/api/v1/auth/me"       401

# 3. Public endpoints
check "OpenAPI docs"          GET  "/docs"                 200
check "OpenAPI spec"          GET  "/api/v1/openapi.json"  200

# 4. Tenant-scoped (should reject without auth)
check "Employees (no auth)"   GET  "/api/v1/employees"     401
check "Payroll runs (no auth)" GET "/api/v1/payroll-runs"  401
check "Leaves (no auth)"      GET  "/api/v1/leaves"        401

# 5. Platform admin (should reject without super_admin)
check "Platform companies (no auth)" GET "/api/v1/platform/companies" 401

# 6. Export endpoints (should reject without auth)
check "Export employees (no auth)" GET "/api/v1/exports/employees" 401

echo ""
echo "============================================"
echo "  Results: ${PASS} passed, ${FAIL} failed, ${SKIP} skipped"
echo "============================================"

# Generate markdown report
REPORT_FILE="/tmp/smoke-post-deploy-$(date -u +%Y%m%d-%H%M%S).md"
{
  echo "# Smoke Post-Deploy Report"
  echo ""
  echo "- **Target:** ${API_BASE}"
  echo "- **Date:** $(date -u +%Y-%m-%dT%H:%M:%SZ)"
  echo "- **Result:** ${PASS} passed, ${FAIL} failed"
  echo ""
  echo "| Status | Check | Endpoint | HTTP |"
  echo "|--------|-------|----------|------|"
  for r in "${RESULTS[@]}"; do
    IFS='|' read -r st nm ep ht <<< "$r"
    echo "| ${st} | ${nm} | ${ep} | ${ht} |"
  done
} > "$REPORT_FILE"

echo "Report: ${REPORT_FILE}"

if [[ $FAIL -gt 0 ]]; then
  exit 1
fi
exit 0
