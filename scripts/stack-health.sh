#!/usr/bin/env bash
# stack-health.sh — Sonde de santé des stacks d'un tier (épic #8670, issue #8672).
#
# Généralisation multi-tiers de scripts/dev-stack-health.sh (#8661). Lit le
# registre du tier et sonde l'endpoint /health de chaque stack qui possède une
# api.health_url. Sortie JSONL (une ligne JSON par stack) et code de sortie
# global :
#   0 — toutes les stacks sondables répondent 200
#   1 — au moins une stack sondable est malade
#   2 — erreur d'usage / prérequis
#
# Le tier est déduit du préfixe de l'identifiant de stack quand il n'est pas
# explicite :
#   --tier dev  → chaque stack porte api.health_url (Render free dev)
#   --tier prod → idem, plus web.url / admin.url pour les fronts
# Les registres sont lus dans deploy/<tier>-stacks.json.
#
# Aucun secret requis : sonde HTTP publique (fail-open avec log clair).
#
# Usage :
#   scripts/stack-health.sh                            # tier dev, toutes les stacks
#   scripts/stack-health.sh --tier prod                # tier prod
#   scripts/stack-health.sh --tier prod --stack b      # une seule stack
#   scripts/stack-health.sh --tier prod --active       # uniquement la stack active
#   scripts/stack-health.sh --tier prod --pretty       # sortie humaine
#   scripts/stack-health.sh --tier prod --surfaces     # sonde aussi les fronts
#
# Exemples :
#   scripts/stack-health.sh --tier prod --active --pretty
#   scripts/stack-health.sh --tier prod | jq -s 'map(select(.http_code != 200))'
#
# Compatibilité : scripts/dev-stack-health.sh reste un shim vers ce script.

set -euo pipefail

TIER="dev"
STACK_FILTER=""
PRETTY=0
SURFACES=0
REGISTRY=""

while [[ $# -gt 0 ]]; do
  case "$1" in
    --tier)     TIER="${2:?--tier exige dev|prod|staging}"; shift 2 ;;
    --registry) REGISTRY="${2:?--registry exige un chemin}"; shift 2 ;;
    --stack)    STACK_FILTER="${2:?--stack exige un id (a|b|c)}"; shift 2 ;;
    --active)   STACK_FILTER="__active__"; shift ;;
    --pretty)   PRETTY=1; shift ;;
    --surfaces) SURFACES=1; shift ;;
    -h|--help)
      sed -n '2,26p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'
      exit 0 ;;
    *) echo "Argument inconnu : $1 (voir --help)" >&2; exit 2 ;;
  esac
done

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
if [[ -z "$REGISTRY" ]]; then
  REGISTRY="$ROOT/deploy/${TIER}-stacks.json"
fi

TIMEOUT="${STACK_HEALTH_TIMEOUT:-45}"   # un réveil à froid Render free peut prendre 30-60 s

for bin in jq curl; do
  command -v "$bin" >/dev/null 2>&1 || { echo "Prérequis manquant : $bin" >&2; exit 2; }
done
[[ -f "$REGISTRY" ]] || { echo "Registre introuvable : $REGISTRY" >&2; exit 2; }

if [[ "$STACK_FILTER" == "__active__" ]]; then
  STACK_FILTER="$(jq -r '.active_stack' "$REGISTRY")"
fi

# Sonde une URL et renvoie « code<TAB>latence_ms<TAB>suspended_hint ».
probe_url() {
  local url="$1" started code body
  started="$(date +%s%N)"
  body="$(curl --silent --show-error --max-time "$TIMEOUT" "$url" 2>/dev/null | head -c 300 || true)"
  code="$(curl --silent --output /dev/null --write-out '%{http_code}' --max-time "$TIMEOUT" "$url" 2>/dev/null || echo "000")"
  local latency=$(( ($(date +%s%N) - started) / 1000000 ))
  local suspended="false"
  # Indice de suspension Render : la page « Service Suspended » ne vient pas de l'app.
  # C'est exactement ce qui s'est produit en prod du 2026-09-28 au 2026-10-01.
  if grep -qiE "has been suspended|service suspended" <<<"$body"; then
    suspended="true"
  fi
  printf '%s\t%s\t%s' "$code" "$latency" "$suspended"
}

probe_stack() {
  local stack_id="$1" health_url status
  health_url="$(jq -r --arg s "$stack_id" '.stacks[$s].api.health_url // empty' "$REGISTRY")"
  status="$(jq -r --arg s "$stack_id" '.stacks[$s].status // "unknown"' "$REGISTRY")"

  if [[ -z "$health_url" ]]; then
    jq -nc --arg t "$TIER" --arg s "$stack_id" --arg st "$status" \
      '{tier: $t, stack: $s, status: $st, probe: "skipped", reason: "pas de health_url (stack non provisionnée)"}'
    return 0
  fi

  local out code latency suspended
  out="$(probe_url "$health_url")"
  IFS=$'\t' read -r code latency suspended <<<"$out"

  local probe="sick"
  [[ "$code" == "200" ]] && probe="healthy"

  jq -nc --arg t "$TIER" --arg s "$stack_id" --arg st "$status" --arg url "$health_url" \
         --arg code "$code" --argjson lat "$latency" --arg probe "$probe" \
         --argjson susp "$suspended" \
    '{tier: $t, stack: $s, status: $st, probe: $probe, http_code: ($code | tonumber? // 0),
      latency_ms: $lat, url: $url, render_suspended_hint: $susp}'

  [[ "$probe" == "healthy" ]]
}

# Sonde facultative des fronts (web Vercel, admin Pages). Un front qui répond
# 200 alors que son API est morte est un Piège : il affiche des erreurs au lieu
# d'une page d'incident. Utile pour arbitrer une bascule.
probe_surfaces() {
  local stack_id="$1" web admin out code latency suspended
  web="$(jq -r --arg s "$stack_id" '.stacks[$s].web.url // empty' "$REGISTRY")"
  admin="$(jq -r --arg s "$stack_id" '.stacks[$s].admin.url // empty' "$REGISTRY")"

  for pair in "web:$web" "admin:$admin"; do
    local kind="${pair%%:*}" url="${pair#*:}"
    [[ -n "$url" ]] || continue
    out="$(probe_url "$url")"
    IFS=$'\t' read -r code latency suspended <<<"$out"
    jq -nc --arg t "$TIER" --arg s "$stack_id" --arg k "$kind" --arg url "$url" \
           --arg code "$code" --argjson lat "$latency" \
      '{tier: $t, stack: $s, surface: $k, http_code: ($code | tonumber? // 0), latency_ms: $lat, url: $url}'
    [[ "$code" == "200" ]] || overall=1
  done
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
  if [[ "$SURFACES" -eq 1 ]]; then
    probe_surfaces "$s" >/dev/null || overall=1
  fi
done

if [[ "$PRETTY" -eq 1 ]]; then
  printf '%-6s %-10s %-9s %-6s %-8s %s\n' TIER STACK STATUS PROBE HTTP LATENCE
  for line in "${results[@]}"; do
    jq -r '[.tier, .stack, .status, .probe, (.http_code|tostring), ((.latency_ms // 0|tostring) + " ms")] | @tsv' <<<"$line" \
      | awk -F'\t' '{printf "%-6s %-10s %-9s %-6s %-8s %s\n", $1, $2, $3, $4, $5, $6}'
  done
else
  for line in "${results[@]}"; do
    echo "$line"
  done
fi

exit "$overall"
