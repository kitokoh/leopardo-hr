#!/usr/bin/env bash
# dev-stack-health.sh — Sonde de santé des stacks du tier dev (épic #8659, issue #8661).
#
# Lit deploy/dev-stacks.json et sonde l'endpoint /health de chaque stack qui
# possède une api.health_url. Sortie JSONL (une ligne JSON par stack) + code
# de sortie global :
#   0 — toutes les stacks sondables répondent 200
#   1 — au moins une stack sondable est malade
#   2 — erreur d'usage / prérequis
#
# Aucun secret requis : sonde HTTP publique (fail-open avec log clair).
#
# Usage :
#   scripts/dev-stack-health.sh                 # toutes les stacks sondables
#   scripts/dev-stack-health.sh --stack b       # une seule stack
#   scripts/dev-stack-health.sh --active        # uniquement la stack active
#   scripts/dev-stack-health.sh --pretty        # sortie humaine (table)
#
# Exemples :
#   scripts/dev-stack-health.sh --active --pretty
#   scripts/dev-stack-health.sh | jq -s 'map(select(.http_code != 200))'

set -euo pipefail

REGISTRY="${DEV_STACKS_REGISTRY:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/deploy/dev-stacks.json}"
TIMEOUT="${DEV_STACK_HEALTH_TIMEOUT:-45}"   # un réveil à froid Render free peut prendre 30-60 s
STACK_FILTER=""
PRETTY=0

while [[ $# -gt 0 ]]; do
  case "$1" in
    --stack)  STACK_FILTER="${2:?--stack exige un id (a|b|c)}"; shift 2 ;;
    --active) STACK_FILTER="__active__"; shift ;;
    --pretty) PRETTY=1; shift ;;
    -h|--help)
      sed -n '2,22p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'
      exit 0 ;;
    *) echo "Argument inconnu : $1 (voir --help)" >&2; exit 2 ;;
  esac
done

for bin in jq curl; do
  command -v "$bin" >/dev/null 2>&1 || { echo "Prérequis manquant : $bin" >&2; exit 2; }
done
[[ -f "$REGISTRY" ]] || { echo "Registre introuvable : $REGISTRY" >&2; exit 2; }

if [[ "$STACK_FILTER" == "__active__" ]]; then
  STACK_FILTER="$(jq -r '.active_stack' "$REGISTRY")"
fi

probe_stack() {
  local stack_id="$1" health_url status
  health_url="$(jq -r --arg s "$stack_id" '.stacks[$s].api.health_url // empty' "$REGISTRY")"
  status="$(jq -r --arg s "$stack_id" '.stacks[$s].status // "unknown"' "$REGISTRY")"

  if [[ -z "$health_url" ]]; then
    jq -nc --arg s "$stack_id" --arg st "$status" \
      '{stack: $s, status: $st, probe: "skipped", reason: "pas de health_url (stack non provisionnée)"}'
    return 0
  fi

  local started http_code latency body_excerpt
  started="$(date +%s%N)"
  body_excerpt="$(curl --silent --show-error --max-time "$TIMEOUT" "$health_url" 2>/dev/null | head -c 300 || true)"
  http_code="$(curl --silent --output /dev/null --write-out '%{http_code}' --max-time "$TIMEOUT" "$health_url" 2>/dev/null || echo "000")"
  latency=$(( ($(date +%s%N) - started) / 1000000 ))

  local probe="sick"
  [[ "$http_code" == "200" ]] && probe="healthy"

  # Indice de suspension Render : la page « Service Suspended » ne vient pas de l'app.
  local suspended_hint="false"
  if grep -qi "has been suspended by its owner" <<<"$body_excerpt"; then
    suspended_hint="true"
  fi

  jq -nc --arg s "$stack_id" --arg st "$status" --arg url "$health_url" \
         --arg code "$http_code" --argjson lat "$latency" --arg probe "$probe" \
         --argjson susp "$suspended_hint" \
    '{stack: $s, status: $st, probe: $probe, http_code: ($code | tonumber? // 0),
      latency_ms: $lat, url: $url, render_suspended_hint: $susp}'

  [[ "$probe" == "healthy" ]]
}

overall=0
results=()
if [[ -n "$STACK_FILTER" ]]; then
  candidates="$STACK_FILTER"
else
  candidates="$(jq -r '.stacks | keys[]' "$REGISTRY")"
fi

for s in $candidates; do
  if ! jq -e --arg s "$s" '.stacks[$s]' "$REGISTRY" >/dev/null; then
    echo "Stack inconnue dans le registre : $s" >&2
    exit 2
  fi
  line="$(probe_stack "$s")" || overall=1
  results+=("$line")
done

if [[ "$PRETTY" -eq 1 ]]; then
  printf '%-6s %-10s %-9s %-6s %-9s %s\n' STACK STATUS PROBE HTTP LATENCE URL
  for line in "${results[@]}"; do
    jq -r '[.stack, .status, .probe, (.http_code|tostring), ((.latency_ms // 0|tostring) + " ms"), (.url // "-")] | @tsv' <<<"$line" \
      | awk -F'\t' '{printf "%-6s %-10s %-9s %-6s %-9s %s\n", $1, $2, $3, $4, $5, $6}'
  done
else
  for line in "${results[@]}"; do
    echo "$line"
  done
fi

exit "$overall"
