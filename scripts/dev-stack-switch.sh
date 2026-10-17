#!/usr/bin/env bash
# dev-stack-switch.sh — Bascule le tier dev vers une autre stack (épic #8659, issue #8662).
#
# Ce que fait une bascule (données dev FICTIVES — reseed possible sans risque) :
#   1. vérifie que la stack cible existe et est saine (healthcheck 200) ;
#   2. met à jour les variables du dépôt : DEV_ACTIVE_STACK, DEV_API_BASE_URL
#      (consommée par deploy-main.yml), DEV_WEB_URL, DEV_ADMIN_URL quand la
#      cible les définit ;
#   3. (--seed) ré-initialise la base de la cible via RESET_TEST_DB_ONCE
#      (one-shot fail-closed DEPLOY_TIER=dev, cf. api/docker-entrypoint.sh) ;
#   4. re-vérifie la santé post-bascule et affiche le runbook de fin.
#
# Prérequis : gh authentifié (repo kitokoh/leopardo-hr), jq, curl.
#   --seed exige en plus RENDER_API_KEY (clé du compte Render de la CIBLE).
#
# Usage :
#   scripts/dev-stack-switch.sh <stack> [--force] [--seed] [--dry-run]
#   scripts/dev-stack-switch.sh b --seed
#
# La PROD n'est jamais touchée : ce script n'écrit que des variables DEV_*.

set -euo pipefail

REPO="${GITHUB_REPOSITORY:-kitokoh/leopardo-hr}"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
REGISTRY="${DEV_STACKS_REGISTRY:-$ROOT/deploy/dev-stacks.json}"

TARGET=""
FORCE=0
SEED=0
DRY=0

while [[ $# -gt 0 ]]; do
  case "$1" in
    --force)   FORCE=1; shift ;;
    --seed)    SEED=1; shift ;;
    --dry-run) DRY=1; shift ;;
    -h|--help) sed -n '2,21p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'; exit 0 ;;
    -*)        echo "Option inconnue : $1" >&2; exit 2 ;;
    *)         TARGET="$1"; shift ;;
  esac
done

[[ -n "$TARGET" ]] || { echo "Usage : dev-stack-switch.sh <stack> [--force] [--seed] [--dry-run]" >&2; exit 2; }
for bin in gh jq curl; do
  command -v "$bin" >/dev/null 2>&1 || { echo "Prérequis manquant : $bin" >&2; exit 2; }
done

log()  { printf '\033[1;34m[switch]\033[0m %s\n' "$*"; }
warn() { printf '\033[1;33m[switch]\033[0m %s\n' "$*" >&2; }
die()  { printf '\033[1;31m[switch]\033[0m %s\n' "$*" >&2; exit 1; }

jq -e --arg s "$TARGET" '.stacks[$s]' "$REGISTRY" >/dev/null \
  || die "Stack inconnue : $TARGET (registre $REGISTRY)"

CURRENT="$(jq -r '.active_stack' "$REGISTRY")"
[[ "$TARGET" != "$CURRENT" ]] || { log "La stack $TARGET est déjà active. Rien à faire."; exit 0; }

STATUS="$(jq -r --arg s "$TARGET" '.stacks[$s].status' "$REGISTRY")"
BASE_URL="$(jq -r --arg s "$TARGET" '.stacks[$s].api.base_url // empty' "$REGISTRY")"
WEB_URL="$(jq -r --arg s "$TARGET" '.stacks[$s].web.url // empty' "$REGISTRY")"
ADMIN_URL="$(jq -r --arg s "$TARGET" '.stacks[$s].admin.url // empty' "$REGISTRY")"

[[ -n "$BASE_URL" ]] || die "Stack $TARGET sans api.base_url — provisionnement incomplet (voir docs/ops/DEV_STACK_ROTATION.md § Onboarding)."

if [[ "$STATUS" != "ready" && "$STATUS" != "active" ]]; then
  if [[ "$FORCE" -eq 1 ]]; then
    warn "Stack $TARGET au statut '$STATUS' — --force accepté, à vos risques."
  else
    die "Stack $TARGET au statut '$STATUS' (attendu: ready). Vérifiez scripts/dev-stack-health.sh, ou --force assumé."
  fi
fi

# ── 1. Santé de la cible AVANT bascule ──────────────────────────────────────
log "Sonde de la cible $TARGET ($BASE_URL/health)…"
http="$(curl --silent --output /dev/null --write-out '%{http_code}' --max-time 90 "$BASE_URL/health" || echo 000)"
if [[ "$http" != "200" ]]; then
  if [[ "$FORCE" -eq 1 ]]; then
    warn "Cible malade (HTTP $http) — --force accepté."
  else
    die "Cible malade (HTTP $http). Réveillez-la (cold start = réessayer dans 60 s) ou corrigez-la avant de basculer. Jamais de bascule vers une stack malade."
  fi
fi
log "Cible saine (HTTP $http)."

if [[ "$DRY" -eq 1 ]]; then
  log "[dry-run] Mettrait à jour : DEV_ACTIVE_STACK=$TARGET, DEV_API_BASE_URL=$BASE_URL${WEB_URL:+, DEV_WEB_URL=$WEB_URL}${ADMIN_URL:+, DEV_ADMIN_URL=$ADMIN_URL}"
  [[ "$SEED" -eq 1 ]] && log "[dry-run] Lancerait un reseed one-shot sur la cible."
  exit 0
fi

# ── 2. Pointeurs runtime (variables du dépôt) ───────────────────────────────
log "Mise à jour des variables du dépôt $REPO…"
gh variable set DEV_ACTIVE_STACK --body "$TARGET" --repo "$REPO"
gh variable set DEV_API_BASE_URL --body "$BASE_URL" --repo "$REPO"
[[ -n "$WEB_URL" ]]   && gh variable set DEV_WEB_URL   --body "$WEB_URL"   --repo "$REPO"
[[ -n "$ADMIN_URL" ]] && gh variable set DEV_ADMIN_URL --body "$ADMIN_URL" --repo "$REPO"
log "Variables à jour : DEV_ACTIVE_STACK=$TARGET, DEV_API_BASE_URL=$BASE_URL"

# ── 3. Reseed one-shot de la base cible (optionnel) ─────────────────────────
if [[ "$SEED" -eq 1 ]]; then
  SERVICE_ID="$(jq -r --arg s "$TARGET" '.stacks[$s].api.service_id // empty' "$REGISTRY")"
  [[ -n "$SERVICE_ID" ]] || die "--seed exige api.service_id dans le registre pour $TARGET."
  [[ -n "${RENDER_API_KEY:-}" ]] || die "--seed exige RENDER_API_KEY (clé du compte Render de la cible) dans l'environnement."

  api() { curl --silent --show-error --request "$1" --header "Authorization: Bearer $RENDER_API_KEY" \
               --header "Content-Type: application/json" ${3:+--data "$3"} "https://api.render.com/v1/$2"; }

  log "Pose de RESET_TEST_DB_ONCE=true sur $SERVICE_ID…"
  api PUT "services/$SERVICE_ID/env-vars/RESET_TEST_DB_ONCE" "" '{"value":"true"}' >/dev/null

  log "Redémarrage du service (reset + reseed au boot, one-shot fail-closed DEPLOY_TIER=dev)…"
  api POST "services/$SERVICE_ID/restart" >/dev/null

  log "Attente du retour de santé (max 8 min, cold start + migrations + seed)…"
  ok=""
  for _ in $(seq 1 48); do
    sleep 10
    code="$(curl --silent --output /dev/null --write-out '%{http_code}' --max-time 30 "$BASE_URL/health" || echo 000)"
    [[ "$code" == "200" ]] && { ok=1; break; }
  done

  log "Retrait de RESET_TEST_DB_ONCE (sécurité one-shot)…"
  api PUT "services/$SERVICE_ID/env-vars/RESET_TEST_DB_ONCE" "" '{"value":"false"}' >/dev/null

  [[ -n "$ok" ]] || die "La cible n'est pas revenue saine après reseed — vérifier les logs Render $SERVICE_ID."
  log "Reseed terminé, cible saine."
fi

# ── 4. Vérification post-bascule ────────────────────────────────────────────
code="$(curl --silent --output /dev/null --write-out '%{http_code}' --max-time 90 "$BASE_URL/health" || echo 000)"
[[ "$code" == "200" ]] || warn "Post-bascule : HTTP $code — vérifiez la stack $TARGET."

cat <<EOF

✅ Bascule dev terminée : stack $CURRENT → $TARGET

Pour finir proprement :
  1. Mettre à jour le registre : deploy/dev-stacks.json
     (active_stack: "$TARGET", statuts des deux stacks) et commiter.
  2. Fronts : si le front Vercel de la cible n'est pas encore déployé sur main,
     le déployer (voir docs/ops/DEV_STACK_ROTATION.md § Fronts).
  3. Annoncer la bascule à l'équipe (l'URL d'API dev a changé).

Rollback : scripts/dev-stack-switch.sh $CURRENT
EOF
