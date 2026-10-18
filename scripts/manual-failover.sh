#!/usr/bin/env bash
# manual-failover.sh — Bascule de stack "une action" + propagation du proxy (issue #8734).
#
# Ordonnateur d'urgence : fait en UNE commande ce que la bascule automatique
# (watchdog) fait par étapes, PLUS le chaînon que la bascule auto ne couvre pas
# encore : la variable API_PROXY_TARGET du proxy Next (Vercel), lue au RUNTIME
# par #8710. Sans elle, le proxy continuerait d'appeler l'ancienne stack.
#
# Périmètre couvert par l'automatisation complète (dev) :
#   Render  — détection 503/suspension + bascule DEV_ACTIVE_STACK/DEV_API_BASE_URL
#             (dev-keepalive.yml watchdog → dev-stack-switch.yml).
# Périmètre couvert par CE script (une action) :
#   Render  — bascule manuelle immédiate (reprend stack-switch.sh).
#   Vercel  — propagation API_PROXY_TARGET/NEXT_PUBLIC_API_URL + redeploy du proxy.
# Périmètre NON automatisable en free (aucun webhook de quota exposé) :
#   Neon    — failover base = db-standby-failover.yml (RPO ≤ 6 h) ; prod ne reseed JAMAIS.
#   Upstash — pas de clé API fournie : remplacement manuel du fournisseur cache/session.
#
# Usage :
#   scripts/manual-failover.sh dev b                  # bascule dev → stack b (+ proxy)
#   scripts/manual-failover.sh dev b --dry-run
#   scripts/manual-failover.sh prod b --dry-run       # prod : garde-fous stricts
#
# Variables d'environnement (optionnelles, sinon étapes sautées avec warning) :
#   VERCEL_DEV_TOKEN   — token Vercel du projet dev (projet "leopardo")
#   VERCEL_DEV_PROJECT_ID — prj_RcRVAujaFul8WNZXxgnNKTb9lXdr (défaut)
#   VERCEL_DEV_TEAM_ID    — team_pjMBnfirN9999uUp6Fg9u6F9 (défaut)
#   RENDER_API_KEY     — clé du compte Render de la CIBLE (requis pour --seed dev)

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
REPO="${GITHUB_REPOSITORY:-kitokoh/leopardo-hr}"

TIER=""
TARGET=""
DRY=0

while [[ $# -gt 0 ]]; do
  case "$1" in
    --dry-run) DRY=1; shift ;;
    -h|--help) sed -n '2,38p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'; exit 0 ;;
    *)
      if [[ -z "$TIER" ]]; then TIER="$1"; else TARGET="$1"; fi
      shift ;;
  esac
done

[[ -n "$TIER" && -n "$TARGET" ]] || { echo "Usage : manual-failover.sh <dev|prod> <a|b|c> [--dry-run]" >&2; exit 2; }

log()  { printf '\033[1;34m[failover:%s]\033[0m %s\n' "$TIER" "$*"; }
warn() { printf '\033[1;33m[failover:%s]\033[0m %s\n' "$TIER" "$*" >&2; }
die()  { printf '\033[1;31m[failover:%s]\033[0m %s\n' "$TIER" "$*" >&2; exit 1; }

for bin in gh jq curl; do command -v "$bin" >/dev/null 2>&1 || die "Prérequis manquant : $bin"; done

# ── 1. Bascule Render (logique éprouvée, garde-fous prod) ─────────────────────
if [[ "$DRY" -eq 1 ]]; then
  "$ROOT/scripts/stack-switch.sh" "$TARGET" --tier "$TIER" --dry-run
else
  "$ROOT/scripts/stack-switch.sh" "$TARGET" --tier "$TIER"
fi

# ── 2. Propagation du proxy Vercel (runtime #8710) ────────────────────────────
# Le proxy Next résout sa cible au runtime via API_PROXY_TARGET > BACKEND_API_URL
# > NEXT_PUBLIC_API_URL. Changer de stack = poser ces variables + redeployer le
# proxy (les fonctions serverless figent les env à chaque déploiement).
REGISTRY="$ROOT/deploy/${TIER}-stacks.json"
BASE_URL="$(jq -r --arg s "$TARGET" '.stacks[$s].api.base_url // empty' "$REGISTRY")"
[[ -n "$BASE_URL" ]] || die "Stack $TARGET sans api.base_url — provisionnement incomplet."

if [[ "$TIER" == "prod" ]]; then
  warn "Tier prod : la propagation Vercel n'est PAS automatisée ici (projet leopardo-prod, hors périmètre de ce script dev)."
  warn "  Prod → voir docs/ops/PROD_STACK_ROTATION.md § Bascule (deploy-prod.yml + variables VERCEL_PROD_*)."
  exit 0
fi

if [[ -z "${VERCEL_DEV_TOKEN:-}" ]]; then
  warn "VERCEL_DEV_TOKEN absent — proxy Vercel NON propagé. Le proxy dev continuera d'appeler l'ancienne stack."
  warn "  Poser la variable sur Vercel → projet 'leopardo' → API_PROXY_TARGET=$BASE_URL, puis Redeploy."
  exit 0
fi

PROJECT_ID="${VERCEL_DEV_PROJECT_ID:-prj_RcRVAujaFul8WNZXxgnNKTb9lXdr}"
TEAM_ID="${VERCEL_DEV_TEAM_ID:-team_pjMBnfirN9999uUp6Fg9u6F9}"
VH=(-H "Authorization: Bearer $VERCEL_DEV_TOKEN")

vercel_env() {  # PATCH une variable d'environnement du projet (create si absente)
  local key="$1" value="$2"
  curl -s "${VH[@]}" -X POST "https://api.vercel.com/v10/projects/$PROJECT_ID/env?teamId=$TEAM_ID" \
    -H "Content-Type: application/json" \
    -d "{\"key\":\"$key\",\"value\":\"$value\",\"target\":[\"production\",\"preview\"],\"type\":\"plain\"}" \
    >/dev/null || true
}

if [[ "$DRY" -eq 1 ]]; then
  log "[dry-run] Poserait sur Vercel : API_PROXY_TARGET=$BASE_URL, NEXT_PUBLIC_API_URL=$BASE_URL, puis redeploy du proxy."
  exit 0
fi

log "Propagation Vercel : API_PROXY_TARGET / NEXT_PUBLIC_API_URL → $BASE_URL"
vercel_env "API_PROXY_TARGET" "$BASE_URL"
vercel_env "NEXT_PUBLIC_API_URL" "$BASE_URL"

log "Redéploiement du proxy Vercel (projectId=$PROJECT_ID)…"
DEPLOY=$(curl -s "${VH[@]}" -X POST "https://api.vercel.com/v13/deployments?teamId=$TEAM_ID&forceNew=1" \
  -H "Content-Type: application/json" \
  -d "{\"name\":\"leopardo\",\"project\":\"$PROJECT_ID\",\"target\":\"production\",\"gitSource\":{\"type\":\"github\",\"repoId\":1061111898,\"ref\":\"main\"}}")
DEPLOY_ID=$(python3 -c "import sys,json;print(json.load(sys.stdin).get('id',''))" <<<"$DEPLOY" 2>/dev/null || echo "")
log "Deploy Vercel lancé : ${DEPLOY_ID:-inconnu} (état READY à surveiller dans le dashboard)."

# ── 3. Récapitulatif ──────────────────────────────────────────────────────────
cat <<EOF

✅ Bascule $TIER → stack $TARGET orchestrée.

Récap :
  - Render : stack $TARGET active (variables ${TIER^^}_ACTIVE_STACK / ${TIER^^}_API_BASE_URL).
  - Vercel : API_PROXY_TARGET=$BASE_URL + redeploy du proxy (une action).
  - Neon   : ${TIER^^} — hors périmètre (dev reseed via --seed ; prod = db-standby-failover.yml, jamais de reseed).
  - Upstash : pas de clé fournie — remplacement manuel du fournisseur cache/session si quota atteint.

Vérifier ensuite :
  1. scripts/stack-health.sh --tier $TIER --active --pretty   (200 attendu)
  2. Login réel : curl -X POST <proxy>/api/v1/platform/auth/login  (200 + token)
  3. Aligner deploy/${TIER}-stacks.json (active_stack, statuts) puis commiter.

Rollback : scripts/manual-failover.sh $TIER <ancienne_stack>
EOF
