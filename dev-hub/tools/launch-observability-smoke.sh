#!/usr/bin/env bash
set -euo pipefail

API_URL="${1:-${LAUNCH_API_URL:-https://gestionemployerbackend.onrender.com}}"
WEB_URL="${2:-${LAUNCH_WEB_URL:-https://gestionemployer-backend.vercel.app}}"
ADMIN_URL="${3:-${LAUNCH_ADMIN_URL:-https://leo-admin.pages.dev}}"

API_URL="${API_URL%/}"
WEB_URL="${WEB_URL%/}"
ADMIN_URL="${ADMIN_URL%/}"

REPORT_DIR="${LAUNCH_REPORT_DIR:-dev-hub/observability/reports}"
mkdir -p "${REPORT_DIR}"
REPORT_FILE="${REPORT_DIR}/launch-observability-smoke.json"

TIMEOUT="${LAUNCH_SMOKE_TIMEOUT_SECONDS:-15}"
MAX_P95_MS="${LAUNCH_MAX_P95_MS:-2500}"
RETRIES="${LAUNCH_SMOKE_RETRIES:-5}"
RETRY_DELAY="${LAUNCH_SMOKE_RETRY_DELAY_SECONDS:-15}"

json_escape() {
  local value="$1"
  value="${value//\\/\\\\}"
  value="${value//\"/\\\"}"
  value="${value//$'\n'/\\n}"
  printf '%s' "${value}"
}

probe() {
  local name="$1"
  local url="$2"
  local expected="${3:-200}"

  local tmp metrics status total_seconds total_ms ok error attempt should_retry
  ok=false
  error=""
  status=0
  total_ms=0
  attempt=1

  while [[ "${attempt}" -le "${RETRIES}" ]]; do
    tmp="$(mktemp)"
    metrics="$(curl --silent --show-error --location --max-time "${TIMEOUT}" \
      -H 'Accept: application/json,text/html;q=0.9,*/*;q=0.8' \
      -o "${tmp}" -w '%{http_code} %{time_total}' "${url}" 2>"${tmp}.err" || true)"
    status="${metrics%% *}"
    total_seconds="${metrics##* }"
    if [[ ! "${status}" =~ ^[0-9]+$ ]]; then
      status=0
    else
      status="$(awk -v value="${status}" 'BEGIN { printf "%d", value + 0 }')"
    fi
    if [[ ! "${total_seconds}" =~ ^[0-9]+([.][0-9]+)?$ ]]; then
      total_seconds=0
    fi
    total_ms="$(awk -v seconds="${total_seconds}" 'BEGIN { printf "%d", seconds * 1000 }')"
    error="$(cat "${tmp}.err" 2>/dev/null || true)"
    rm -f "${tmp}" "${tmp}.err"

    if [[ "${status}" == "${expected}" && "${total_ms}" -le "${MAX_P95_MS}" ]]; then
      ok=true
      error=""
      break
    fi

    should_retry=false
    if [[ "${status}" == "0" || "${status}" =~ ^[0-9]+$ && "${status}" -ge 500 || "${total_ms}" -gt "${MAX_P95_MS}" ]]; then
      should_retry=true
    fi

    if [[ "${should_retry}" != true || "${attempt}" -ge "${RETRIES}" ]]; then
      break
    fi

    sleep "${RETRY_DELAY}"
    attempt=$((attempt + 1))
  done

  printf '{"name":"%s","url":"%s","expected_status":%s,"status":%s,"latency_ms":%s,"attempts":%s,"ok":%s,"error":"%s"}' \
    "$(json_escape "${name}")" \
    "$(json_escape "${url}")" \
    "${expected}" \
    "${status:-0}" \
    "${total_ms}" \
    "${attempt}" \
    "${ok}" \
    "$(json_escape "${error}")"
}

checks=()
checks+=("$(probe "api_health" "${API_URL}/api/v1/health" 200)")
checks+=("$(probe "api_docs" "${API_URL}/docs" 200)")
checks+=("$(probe "api_docs_openapi" "${API_URL}/docs/openapi.yaml" 200)")
checks+=("$(probe "web_vitrine" "${WEB_URL}" 200)")
checks+=("$(probe "web_pricing" "${WEB_URL}/pricing" 200)")
checks+=("$(probe "web_demo" "${WEB_URL}/demo" 200)")
checks+=("$(probe "admin_login" "${ADMIN_URL}" 200)")

failed=0
for check in "${checks[@]}"; do
  if ! grep -q '"ok":true' <<< "${check}"; then
    failed=$((failed + 1))
  fi
done

{
  printf '{\n'
  printf '  "generated_at": "%s",\n' "$(date -u +"%Y-%m-%dT%H:%M:%SZ")"
  printf '  "api_url": "%s",\n' "$(json_escape "${API_URL}")"
  printf '  "web_url": "%s",\n' "$(json_escape "${WEB_URL}")"
  printf '  "admin_url": "%s",\n' "$(json_escape "${ADMIN_URL}")"
  printf '  "max_latency_ms": %s,\n' "${MAX_P95_MS}"
  printf '  "retries": %s,\n' "${RETRIES}"
  printf '  "retry_delay_seconds": %s,\n' "${RETRY_DELAY}"
  printf '  "failed": %s,\n' "${failed}"
  printf '  "checks": [\n'
  for i in "${!checks[@]}"; do
    printf '    %s' "${checks[$i]}"
    if [[ "${i}" -lt $((${#checks[@]} - 1)) ]]; then
      printf ','
    fi
    printf '\n'
  done
  printf '  ]\n'
  printf '}\n'
} > "${REPORT_FILE}"

cat "${REPORT_FILE}"

if [[ "${failed}" -gt 0 ]]; then
  echo "Launch observability smoke failed: ${failed} check(s) failed." >&2
  exit 1
fi

echo "Launch observability smoke passed."
