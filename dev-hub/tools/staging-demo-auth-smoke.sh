#!/usr/bin/env bash
# Optional staging demo auth smoke.
# Verifies real tenant and platform login routes only when explicitly enabled.

set -euo pipefail

BASE_URL="${1:-${BASE_URL:-https://gestionemployerbackend.onrender.com}}"
BASE_URL="${BASE_URL%/}"
API_BASE="${BASE_URL}/api/v1"

ENABLED="${STAGING_DEMO_AUTH_SMOKE:-false}"
MANAGER_EMAIL="${STAGING_MANAGER_EMAIL:-fatima.meziane@techcorp-algerie.dz}"
MANAGER_PASSWORD="${STAGING_MANAGER_PASSWORD:-password123}"
EMPLOYEE_EMAIL="${STAGING_EMPLOYEE_EMAIL:-karim.aouad@techcorp-algerie.dz}"
EMPLOYEE_PASSWORD="${STAGING_EMPLOYEE_PASSWORD:-password123}"
PLATFORM_EMAIL="${STAGING_PLATFORM_EMAIL:-admin@leopardo-rh.com}"
PLATFORM_PASSWORD="${STAGING_PLATFORM_PASSWORD:-password123}"

if [[ "${ENABLED}" != "true" ]]; then
  echo "[SKIP] Staging demo auth smoke disabled. Set STAGING_DEMO_AUTH_SMOKE=true to run real demo logins."
  exit 0
fi

require_jq() {
  if ! command -v jq >/dev/null 2>&1; then
    echo "[FAIL] jq is required for staging demo auth smoke." >&2
    exit 1
  fi
}

post_json() {
  local url="$1"
  local payload="$2"
  curl --silent --show-error --fail-with-body \
    --max-time 30 \
    -H 'Accept: application/json' \
    -H 'Content-Type: application/json' \
    -X POST \
    -d "${payload}" \
    "${url}"
}

get_json() {
  local url="$1"
  local token="$2"
  curl --silent --show-error --fail-with-body \
    --max-time 30 \
    -H 'Accept: application/json' \
    -H "Authorization: Bearer ${token}" \
    "${url}"
}

login_tenant_user() {
  local label="$1"
  local email="$2"
  local password="$3"
  local expected_role="$4"
  local expected_manager_role="${5:-}"

  echo "[INFO] Logging in ${label} (${email})..." >&2
  local response
  response=$(post_json "${API_BASE}/auth/login" "$(jq -n --arg email "${email}" --arg password "${password}" '{email: $email, password: $password, device_name: "staging-demo-auth-smoke"}')")

  local token
  token=$(jq -r '.token // .data.token // empty' <<< "${response}")
  if [[ -z "${token}" ]]; then
    echo "[FAIL] ${label} login did not return a token." >&2
    exit 1
  fi

  local me
  me=$(get_json "${API_BASE}/auth/me" "${token}")
  local role
  role=$(jq -r '.data.role // empty' <<< "${me}")
  local manager_role
  manager_role=$(jq -r '.data.manager_role // empty' <<< "${me}")

  if [[ "${role}" != "${expected_role}" ]]; then
    echo "[FAIL] ${label} role mismatch: expected ${expected_role}, got ${role}." >&2
    exit 1
  fi

  if [[ -n "${expected_manager_role}" && "${manager_role}" != "${expected_manager_role}" ]]; then
    echo "[FAIL] ${label} manager_role mismatch: expected ${expected_manager_role}, got ${manager_role}." >&2
    exit 1
  fi

  echo "[PASS] ${label} login -> /auth/me (${role}${manager_role:+/${manager_role}})." >&2
  printf '%s' "${token}"
}

login_platform_user() {
  echo "[INFO] Logging in platform super-admin (${PLATFORM_EMAIL})..."
  local response
  response=$(post_json "${API_BASE}/platform/auth/login" "$(jq -n --arg email "${PLATFORM_EMAIL}" --arg password "${PLATFORM_PASSWORD}" '{email: $email, password: $password}')")

  local token
  token=$(jq -r '.token // .data.token // empty' <<< "${response}")
  if [[ -z "${token}" ]]; then
    echo "[FAIL] Platform login did not return a token." >&2
    exit 1
  fi

  local me
  me=$(get_json "${API_BASE}/platform/auth/me" "${token}")
  local role
  role=$(jq -r '.data.role // empty' <<< "${me}")
  if [[ "${role}" != "super_admin" ]]; then
    echo "[FAIL] Platform role mismatch: expected super_admin, got ${role}." >&2
    exit 1
  fi

  echo "[PASS] Platform login -> /platform/auth/me (${role})."
}

require_jq

echo "=== Staging demo auth smoke: ${BASE_URL} ==="
manager_token=$(login_tenant_user "manager RH" "${MANAGER_EMAIL}" "${MANAGER_PASSWORD}" "manager" "rh")
employee_token=$(login_tenant_user "employee" "${EMPLOYEE_EMAIL}" "${EMPLOYEE_PASSWORD}" "employee")
login_platform_user

get_json "${API_BASE}/dashboard/summary" "${manager_token}" >/dev/null
echo "[PASS] Manager token opens /dashboard/summary."

get_json "${API_BASE}/attendance/today" "${employee_token}" >/dev/null
echo "[PASS] Employee token opens /attendance/today."

echo "=== Staging demo auth smoke passed ==="
