#!/usr/bin/env bash
# check-render-env-parity.sh — Parité env attendue (render.yaml) ↔ env réellement
# appliquée sur Render (issue #5172).
#
# Contexte : le compte Render `africanovatech` dévie de render.yaml — l'env live du
# web service `gestionemployerbackend` portait QUEUE_CONNECTION=database,
# SESSION_DRIVER=file, CACHE_STORE=file alors que render.yaml prévoit redis.
# Ce script détecte ce type d'écart (et les régressions futures) :
#   - en comparant les variables statiques de render.yaml (blocs envVars) avec des
#     snapshots KEY=VALUE exportés depuis le dashboard Render (un fichier par
#     service) ;
#   - et/ou en interrogeant GET /api/v1/health (driver de queue effectif).
#
# Comparaison :
#   - clés à VALEUR comparée (dérive critique) : QUEUE_CONNECTION, SESSION_DRIVER,
#     CACHE_STORE, REDIS_CLIENT, APP_ENV, DB_CONNECTION, DB_SEARCH_PATH ;
#   - autres clés statiques (MAIL_*, APP_DEBUG, …) : présence seule — la valeur
#     MAIL_MAILER dévie volontairement (smtp dans render.yaml vs mailgun en prod,
#     #5139 : l'egress SMTP de Render est bloqué) ;
#   - clés dynamiques (fromService/fromDatabase/generateValue) et secrets
#     (sync: false) : non comparées (résolues ou posées à la main dans le dashboard).
#
# Snapshots : exporter l'env de chaque service (dashboard → service → Environment →
# Export/Copy) vers un fichier KEY=VALUE. Les noms par défaut (render-*.env.txt)
# matchent *.env.* du .gitignore → ne JAMAIS les committer.
#
# Usage :
#   dev-hub/tools/check-render-env-parity.sh [options]
#
# Options :
#   --render-yaml FILE    chemin render.yaml (défaut : ./render.yaml)
#   --web-env FILE        snapshot env du web service (défaut : render-web.env.txt)
#   --worker-env FILE     snapshot env du worker queue (défaut : render-worker.env.txt)
#   --scheduler-env FILE  snapshot env du scheduler (défaut : render-scheduler.env.txt)
#   --live                vérifie le driver de queue via GET /api/v1/health
#   --api-url URL         URL API pour --live (défaut : $LEOPARDO_API_URL ou
#                         https://gestionemployerbackend.onrender.com)
#   --help                cette aide
#
# Exit codes : 0 = parité OK · 1 = écart détecté / erreur · 2 = usage invalide.
set -euo pipefail

# ─────────────────────────────────────────────────────────────────────────────
# Config & CLI
# ─────────────────────────────────────────────────────────────────────────────

RENDER_YAML="${RENDER_YAML:-./render.yaml}"
WEB_ENV="${RENDER_WEB_ENV:-render-web.env.txt}"
WORKER_ENV="${RENDER_WORKER_ENV:-render-worker.env.txt}"
SCHEDULER_ENV="${RENDER_SCHEDULER_ENV:-render-scheduler.env.txt}"
API_URL="${LEOPARDO_API_URL:-https://gestionemployerbackend.onrender.com}"
DO_LIVE=false

usage() {
  sed -n '2,40p' "$0" | sed 's/^# \{0,1\}//'
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --render-yaml) RENDER_YAML="$2"; shift 2 ;;
    --web-env) WEB_ENV="$2"; shift 2 ;;
    --worker-env) WORKER_ENV="$2"; shift 2 ;;
    --scheduler-env) SCHEDULER_ENV="$2"; shift 2 ;;
    --api-url) API_URL="$2"; shift 2 ;;
    --live) DO_LIVE=true; shift ;;
    --help|-h) usage; exit 0 ;;
    *) echo "::error::Option inconnue : $1 (--help)" >&2; exit 2 ;;
  esac
done

# Clés dont la VALEUR doit correspondre à render.yaml (dérive critique #5172).
VALUE_KEYS="QUEUE_CONNECTION SESSION_DRIVER CACHE_STORE REDIS_CLIENT APP_ENV DB_CONNECTION DB_SEARCH_PATH"

# ─────────────────────────────────────────────────────────────────────────────
# Helpers
# ─────────────────────────────────────────────────────────────────────────────

# Extrait de render.yaml les variables statiques « SERVICE|KEY=VALUE » (une par
# ligne) ; ignore fromService/fromDatabase/generateValue et sync: false (secrets).
expected_env() {
  awk '
    {
      line = $0
      if (line ~ /^  - type: /)            { flush(); svc = ""; name = "" }
      else if (line ~ /^    name: /)       { name = $2; svc = name }
      else if (line ~ /^      - key: /)    { flush(); key = $3; val = ""; kind = "static" }
      else if (line ~ /^        value: /)  { val = substr(line, index(line, ":") + 2); gsub(/^"|"$/, "", val); gsub(/^'"'"'|'"'"'$/, "", val) }
      else if (line ~ /^        fromService:/)  { kind = "dynamic" }
      else if (line ~ /^        fromDatabase:/) { kind = "dynamic" }
      else if (line ~ /^        generateValue:/) { kind = "generated" }
      else if (line ~ /^        sync:/)    { if (line ~ /false/) kind = "manual" }
      else { flush() }
    }
    END { flush() }
    function flush() {
      if (svc != "" && key != "" && kind == "static" && val != "") print svc "|" key "=" val
      key = ""; val = ""; kind = ""   # ne jamais ré-imprimer la même entrée (commentaires…)
    }
  ' "$RENDER_YAML"
}

# Valeur d'une clé dans un snapshot KEY=VALUE (vide si absente).
snapshot_get() { # $1 fichier, $2 clé
  local file="$1" key="$2" val
  val=$(sed -E 's/^[[:space:]]+#.*$//; /^[[:space:]]*$/d; s/^[[:space:]]+//' "$file" \
        | grep -E "^${key}=" | head -n 1 | cut -d= -f2- | sed -E 's/^"(.*)"$/\1/' || true)
  printf '%s' "$val"
}

# Clé présente dans un snapshot ?
snapshot_has() { # $1 fichier, $2 clé
  grep -qE "^[[:space:]]*${2}=" "$1"
}

# ─────────────────────────────────────────────────────────────────────────────
# 1. Parse render.yaml + comparaison par service
# ─────────────────────────────────────────────────────────────────────────────

if [[ ! -f "$RENDER_YAML" ]]; then
  echo "::error::render.yaml introuvable : $RENDER_YAML" >&2
  exit 2
fi

declare -A SNAP_FILES=(
  [gestionemployerbackend]="$WEB_ENV"
  [leopardo-queue-worker]="$WORKER_ENV"
  [leopardo-scheduler]="$SCHEDULER_ENV"
)
declare -A SNAP_LABELS=(
  [gestionemployerbackend]="web"
  [leopardo-queue-worker]="worker queue"
  [leopardo-scheduler]="scheduler"
)

failures=0
checks=0
warnings=0
declare -A warned_files=()
mapfile -t expected_lines < <(expected_env)

if [[ ${#expected_lines[@]} -eq 0 ]]; then
  echo "::error::Aucune variable statique extraite de render.yaml (parseur awk ?)" >&2
  exit 1
fi

for entry in "${expected_lines[@]}"; do
  service="${entry%%|*}"
  kv="${entry#*|}"
  key="${kv%%=*}"
  expected="${kv#*=}"

  snap_file="${SNAP_FILES[$service]:-}"
  [[ -z "$snap_file" ]] && continue
  label="${SNAP_LABELS[$service]:-$service}"

  if [[ ! -f "$snap_file" ]]; then
    if [[ -z "${warned_files[$snap_file]:-}" ]]; then
      echo "⚠ [${label}] non vérifié : snapshot absent ($snap_file) — exporter l'env depuis le dashboard Render (ne pas committer)."
      warned_files["$snap_file"]=1
    fi
    warnings=$((warnings + 1))
    continue
  fi

  if ! snapshot_has "$snap_file" "$key"; then
    echo "✗ [${label}] ${key} ABSENT du snapshot (attendu : ${expected})"
    failures=$((failures + 1))
    continue
  fi

  if [[ " $VALUE_KEYS " == *" $key "* ]]; then
    actual="$(snapshot_get "$snap_file" "$key")"
    if [[ "$actual" != "$expected" ]]; then
      echo "✗ [${label}] ${key} : attendu '${expected}', snapshot '${actual}' — dérive env (voir runbook docs/OPS/RENDER_QUEUE_WORKERS.md, étape 2)."
      failures=$((failures + 1))
    else
      echo "✓ [${label}] ${key}=${actual}"
      checks=$((checks + 1))
    fi
  else
    echo "✓ [${label}] ${key} présent (valeur non comparée)"
    checks=$((checks + 1))
  fi
done

# ─────────────────────────────────────────────────────────────────────────────
# 2. Mode --live : driver de queue effectif via /api/v1/health
# ─────────────────────────────────────────────────────────────────────────────

if [[ "$DO_LIVE" == true ]]; then
  echo "── Live : GET ${API_URL}/api/v1/health ──"
  health_json="$(curl -fsS --max-time 20 "${API_URL}/api/v1/health" 2>/dev/null || true)"
  if [[ -z "$health_json" ]]; then
    echo "✗ [live] /api/v1/health injoignable (${API_URL})"
    failures=$((failures + 1))
  else
    if command -v jq >/dev/null 2>&1; then
      status="$(jq -r '.status // "?"' <<<"$health_json")"
      queue_driver="$(jq -r '.checks.queue.driver // "?"' <<<"$health_json")"
      redis_status="$(jq -r '.checks.redis.status // "?"' <<<"$health_json")"
    else
      status="$(grep -oE '"status":"[^"]*"' <<<"$health_json" | head -n1 | cut -d'"' -f4)"
      queue_driver="$(grep -oE '"driver":"[^"]*"' <<<"$health_json" | head -n1 | cut -d'"' -f4)"
      redis_status="$(grep -oE '"status":"[^"]*"' <<<"$health_json" | tail -n1 | cut -d'"' -f4)"
    fi
    [[ "$status" == "ok" ]] && echo "✓ [live] status=ok" || { echo "✗ [live] status=${status:-vide}"; failures=$((failures + 1)); }
    if [[ "$queue_driver" == "redis" ]]; then
      echo "✓ [live] queue.driver=redis"
      checks=$((checks + 1))
    else
      echo "✗ [live] queue.driver=${queue_driver:-vide} (attendu redis) — QUEUE_CONNECTION dérive sur le web service."
      failures=$((failures + 1))
    fi
    if [[ "$redis_status" == "pong" || "$redis_status" == "skipped" ]]; then
      echo "✓ [live] redis.status=${redis_status}"
    else
      echo "✗ [live] redis.status=${redis_status:-vide} — Redis injoignable depuis le web service."
      failures=$((failures + 1))
    fi
  fi
fi

# ─────────────────────────────────────────────────────────────────────────────
# 3. Bilan
# ─────────────────────────────────────────────────────────────────────────────

echo "── Bilan ──"
echo "Comparaisons OK : ${checks} · Écarts : ${failures} · Non vérifiés (snapshot absent) : ${warnings}"

if [[ "$failures" -gt 0 ]]; then
  echo "✗ Parité env KO — voir docs/OPS/RENDER_QUEUE_WORKERS.md (étapes 1-2)."
  exit 1
fi
if [[ "$warnings" -gt 0 && "$DO_LIVE" == false ]]; then
  echo "⚠ Parité partielle : des services n'ont pas pu être vérifiés (snapshots manquants)."
fi
echo "✓ Parité env render.yaml ↔ Render OK."
exit 0
