#!/usr/bin/env bash
# Smoke post-deploy — verifies core API endpoints after a deploy.
# Usage: ./smoke-post-deploy.sh [BASE_URL]
# Exit code: 0 = all pass, 1 = at least one failure.

set -euo pipefail

BASE_URL="${1:-https://gestionemployerbackend.onrender.com}"
PASS=0
FAIL=0

check() {
  local label="$1"
  local url="$2"
  local expected_status="$3"

  status=$(curl -s -o /dev/null -w "%{http_code}" \
    -H "Accept: application/json" \
    --max-time 15 "$url" 2>/dev/null || echo "000")

  if [ "$status" = "$expected_status" ]; then
    echo "[PASS] $label — HTTP $status"
    PASS=$((PASS + 1))
  else
    echo "[FAIL] $label — expected $expected_status, got $status"
    FAIL=$((FAIL + 1))
  fi
}

echo "=== Smoke Post-Deploy: $BASE_URL ==="
echo ""

# 1. Health endpoints
check "Health live"       "$BASE_URL/api/v1/health/live"   "200"
check "Health ready"      "$BASE_URL/api/v1/health/ready"  "200"

# 2. Auth — unauthenticated should return 401
check "Auth me (no token)" "$BASE_URL/api/v1/auth/me"     "401"

# 3. Tenant read — unauthenticated should return 401
check "Employees list"    "$BASE_URL/api/v1/employees"     "401"

# 4. Export endpoint — unauthenticated should return 401
check "Bank export"       "$BASE_URL/api/v1/bank-exports"  "401"

# 5. OpenAPI docs page
check "OpenAPI docs"      "$BASE_URL/docs"                 "200"

# 6. Platform auth — wrong credentials should return 422 or 401
status=$(curl -s -o /dev/null -w "%{http_code}" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -X POST \
  -d '{"email":"smoke@test.invalid","password":"wrong"}' \
  --max-time 15 "$BASE_URL/api/v1/platform/auth/login" 2>/dev/null || echo "000")

if [ "$status" = "422" ] || [ "$status" = "401" ]; then
  echo "[PASS] Platform login (bad creds) — HTTP $status"
  PASS=$((PASS + 1))
else
  echo "[FAIL] Platform login (bad creds) — expected 422/401, got $status"
  FAIL=$((FAIL + 1))
fi

echo ""
echo "=== Results: $PASS passed, $FAIL failed ==="

if [ "$FAIL" -gt 0 ]; then
  exit 1
fi

exit 0
