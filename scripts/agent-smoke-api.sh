#!/usr/bin/env bash
# Agent mission — smoke test des workflows API staging Leopardo RH.
# Usage: STAGING_API=https://... bash scripts/agent-smoke-api.sh
set -uo pipefail

API="${STAGING_API:-https://gestionemployerbackend.onrender.com}/api/v1"
PASS=0; FAIL=0; FAILED_LINES=()

note() { printf '%s\n' "$*"; }
ok()   { PASS=$((PASS+1)); printf '  ✅ %s\n' "$*"; }
ko()   { FAIL=$((FAIL+1)); FAILED_LINES+=("$*"); printf '  ❌ %s\n' "$*"; }

api_call() { # method path token [data]
  local m="$1" p="$2" t="${3:-}" d="${4:-}"
  if [ -n "$t" ]; then
    curl -s -m 30 -X "$m" -H "Authorization: Bearer $t" -H 'Content-Type: application/json' -H 'Accept: application/json' ${d:+-d "$d"} "$API$p"
  else
    curl -s -m 30 -X "$m" -H 'Content-Type: application/json' -H 'Accept: application/json' ${d:+-d "$d"} "$API$p"
  fi
}
http_code() { # method path token [data]
  local m="$1" p="$2" t="${3:-}" d="${4:-}"
  if [ -n "$t" ]; then
    curl -s -o /dev/null -w '%{http_code}' -m 30 -X "$m" -H "Authorization: Bearer $t" -H 'Content-Type: application/json' ${d:+-d "$d"} "$API$p"
  else
    curl -s -o /dev/null -w '%{http_code}' -m 30 -X "$m" -H 'Content-Type: application/json' ${d:+-d "$d"} "$API$p"
  fi
}

login() {
  api_call POST /auth/login "" "{\"email\":\"$1\",\"password\":\"$2\",\"device_name\":\"agent-smoke\"}" | jq -r '.token // .data.token // empty'
}

note "== AUTH =="
# #4416 : creds via env (politique #1697 — pas de mot de passe en clair dans le dépôt).
MT=$(login "${LEOPARDO_DEMO_MANAGER_EMAIL:-fatima.meziane@techcorp-algerie.dz}" "${LEOPARDO_DEMO_MANAGER_PASSWORD:-}")
ET=$(login "${LEOPARDO_DEMO_EMPLOYEE_EMAIL:-karim.aouad@techcorp-algerie.dz}" "${LEOPARDO_DEMO_EMPLOYEE_PASSWORD:-}")
PT=$(login "${LEOPARDO_DEMO_ADMIN_EMAIL:-admin@leopardo-rh.com}" "${LEOPARDO_DEMO_PASSWORD:-}")
[ -n "$MT" ] && ok "login manager" || ko "login manager"
[ -n "$ET" ] && ok "login employee" || ko "login employee"
[ -n "$PT" ] && ok "login platform" || ko "login platform"

note "== PROFIL / ME =="
for pair in "manager:$MT" "employee:$ET" "platform:$PT"; do
  label="${pair%%:*}"; tok="${pair#*:}"
  resp=$(api_call GET /auth/me "$tok")
  role=$(echo "$resp" | jq -r '.data.role // empty')
  [ -n "$role" ] && ok "me($label) role=$role" || ko "me($label): $(echo "$resp" | head -c 150)"
done
# Platform admin utilise /platform/auth/me
resp=$(api_call GET /platform/auth/me "$PT")
pname=$(echo "$resp" | jq -r '.data.name // empty')
[ -n "$pname" ] && ok "platform/me name=$pname" || ko "platform/me: $(echo "$resp" | head -c 150)"

note "== ENDPOINTS PUBLICS =="
c=$(http_code GET /health);            [ "$c" = "200" ] && ok "/health 200" || ko "/health $c"
c=$(http_code GET /supported-countries); [ "$c" = "200" ] && ok "/supported-countries 200" || ko "/supported-countries $c"
c=$(http_code GET /features/manifest); [ "$c" = "200" ] && ok "/features/manifest 200" || ko "/features/manifest $c"
c=$(http_code GET /i18n/catalog/fr);   [ "$c" = "200" ] && ok "/i18n/catalog/fr 200" || ko "/i18n/catalog/fr $c"

note "== WORKFLOW MANAGER (paie, employés, présence) =="
c=$(http_code GET /employees "$MT");   [ "$c" = "200" ] && ok "GET /employees 200" || ko "GET /employees $c"
c=$(http_code GET /attendance "$MT");  [ "$c" = "200" ] && ok "GET /attendance 200" || ko "GET /attendance $c"
c=$(http_code GET /schedules "$MT");   [ "$c" = "200" ] && ok "GET /schedules 200" || ko "GET /schedules $c"
# Issue #6682 : route réelle = /payroll-runs (avec tiret) ; l'accès paie est
# réservé comptable/principal — pour le compte manager rh du kit démo, 200
# (si le compte a le rôle) OU 403 (deny RBAC attendu) sont sains ; 404/500 = cassé.
c=$(http_code GET /payroll-runs "$MT"); case "$c" in 200|403) ok "GET /payroll-runs → $c (RBAC OK)";; *) ko "GET /payroll-runs $c";; esac

note "== WORKFLOW EMPLOYEE =="
# Issue #6682 : la route self-service réelle est /me/attendance-anomalies
# (l'ancien /me/attendance n'existe pas — 404).
c=$(http_code GET /me/attendance-anomalies "$ET"); [ "$c" = "200" ] && ok "GET /me/attendance-anomalies 200" || ko "GET /me/attendance-anomalies $c"
c=$(http_code GET /me/pay-slips "$ET");  [ "$c" = "200" ] && ok "GET /me/pay-slips 200" || ko "GET /me/pay-slips $c"

note "== WORKFLOW PLATFORM =="
c=$(http_code GET /platform/companies "$PT"); [ "$c" = "200" ] && ok "GET /platform/companies 200" || ko "GET /platform/companies $c"
c=$(http_code GET /admin/dashboard/stats "$PT"); [ "$c" = "200" ] && ok "GET /admin/dashboard/stats 200" || ko "GET /admin/dashboard/stats $c"
c=$(http_code GET /platform/plans "$PT");  [ "$c" = "200" ] && ok "GET /platform/plans 200" || ko "GET /platform/plans $c"

note "== SECURITE: auth requise =="
c=$(http_code GET /employees); [ "$c" = "401" ] && ok "401 sans token" || ko "GET /employees sans token → $c (attendu 401)"

note ""
note "==================================="
note "RÉSULTAT: $PASS OK / $FAIL ÉCHECS"
note "==================================="
[ "$FAIL" -gt 0 ] && printf '%s\n' "${FAILED_LINES[@]}"
exit $FAIL
