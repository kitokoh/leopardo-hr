#!/usr/bin/env bash
# stack-switch.sh — Bascule un tier (dev ou prod) vers une autre stack (épic #8670).
#
# Généralisation multi-tiers de scripts/dev-stack-switch.sh (#8662). La logique
# de bascule dev est conservée à l'identique ; le tier prod ajoute des
# garde-fous stricts parce que la prod porte des données bêta RÉELLES.
#
# Ce que fait une bascule DEV (données fictives, reseed possible) :
#   1. vérifie que la stack cible existe et est saine (healthcheck 200) ;
#   2. met à jour les variables du dépôt : DEV_ACTIVE_STACK, DEV_API_BASE_URL
#      (consommée par deploy-main.yml), DEV_WEB_URL, DEV_ADMIN_URL ;
#   3. (--seed) ré-initialise la base de la cible via RESET_TEST_DB_ONCE ;
#   4. re-vérifie la santé post-bascule.
#
# Ce que fait une bascule PROD (données bêta, AUCUN reseed) :
#   1. vérifie que la cible est provisionnée et saine (healthcheck 200) ;
#   2. exige que la cible porte la MÊME base que l'active (garde
#      `db.host` identique) : une stack branchée sur une autre base servirait
#      des données périmées — pire qu'une panne ;
#   3. refuse `--seed` et `--force` nus (voir --force-unsafe) ;
#   4. met à jour PROD_ACTIVE_STACK / PROD_API_BASE_URL / PROD_WEB_URL /
#      PROD_ADMIN_URL / PROD_RENDER_SERVICE_URL ;
#   5. re-vérifie la santé post-bascule et affiche la checklist manuelle
#      (fronts Vercel/Pages, retrait des verrous de standby de la queue).
#
# Prérequis : gh authentifié (repo kitokoh/leopardo-hr), jq, curl.
#   --seed exige en plus RENDER_API_KEY (clé du compte Render de la CIBLE).
#
# Usage :
#   scripts/stack-switch.sh <stack> [--tier dev|prod] [--force] [--seed] [--dry-run]
#   scripts/stack-switch.sh b --tier prod --dry-run
#
# Compatibilité : scripts/dev-stack-switch.sh reste un shim vers ce script.

set -euo pipefail

REPO="${GITHUB_REPOSITORY:-kitokoh/leopardo-hr}"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

TIER="dev"
TARGET=""
FORCE=0
FORCE_UNSAFE=0
SEED=0
DRY=0

while [[ $# -gt 0 ]]; do
  case "$1" in
    --tier)        TIER="${2:?--tier exige dev|prod}"; shift 2 ;;
    --force)       FORCE=1; shift ;;
    --force-unsafe) FORCE_UNSAFE=1; shift ;;
    --seed)        SEED=1; shift ;;
    --dry-run)     DRY=1; shift ;;
    -h|--help)     sed -n '2,35p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'; exit 0 ;;
    -*)            echo "Option inconnue : $1" >&2; exit 2 ;;
    *)             TARGET="$1"; shift ;;
  esac
done

[[ -n "$TARGET" ]] || { echo "Usage : stack-switch.sh <stack> [--tier dev|prod] [--force] [--seed] [--dry-run]" >&2; exit 2; }

case "$TIER" in
  dev|prod) ;;
  *) echo "Tier inconnu : $TIER (attendu dev|prod)" >&2; exit 2 ;;
esac

REGISTRY="${STACKS_REGISTRY:-$ROOT/deploy/${TIER}-stacks.json}"
[[ -f "$REGISTRY" ]] || { echo "Registre introuvable : $REGISTRY" >&2; exit 2; }

for bin in gh jq curl; do
  command -v "$bin" >/dev/null 2>&1 || { echo "Prérequis manquant : $bin" >&2; exit 2; }
done

log()  { printf '\033[1;34m[switch:%s]\033[0m %s\n' "$TIER" "$*"; }
warn() { printf '\033[1;33m[switch:%s]\033[0m %s\n' "$TIER" "$*" >&2; }
die()  { printf '\033[1;31m[switch:%s]\033[0m %s\n' "$TIER" "$*" >&2; exit 1; }

jq -e --arg s "$TARGET" '.stacks[$s]' "$REGISTRY" >/dev/null \
  || die "Stack inconnue : $TARGET (registre $REGISTRY)"

CURRENT="$(jq -r '.active_stack' "$REGISTRY")"
[[ "$TARGET" != "$CURRENT" ]] || { log "La stack $TARGET est déjà active. Rien à faire."; exit 0; }

STATUS="$(jq -r --arg s "$TARGET" '.stacks[$s].status' "$REGISTRY")"
BASE_URL="$(jq -r --arg s "$TARGET" '.stacks[$s].api.base_url // empty' "$REGISTRY")"
WEB_URL="$(jq -r --arg s "$TARGET" '.stacks[$s].web.url // empty' "$REGISTRY")"
ADMIN_URL="$(jq -r --arg s "$TARGET" '.stacks[$s].admin.url // empty' "$REGISTRY")"

[[ -n "$BASE_URL" ]] || die "Stack $TARGET sans api.base_url — provisionnement incomplet (voir docs/ops/${TIER^^}_STACK_ROTATION.md § Onboarding)."

if [[ "$STATUS" != "ready" && "$STATUS" != "active" ]]; then
  if [[ "$FORCE" -eq 1 ]]; then
    warn "Stack $TARGET au statut '$STATUS' — --force accepté."
  else
    die "Stack $TARGET au statut '$STATUS' (attendu: ready). Vérifiez scripts/stack-health.sh --tier $TIER."
  fi
fi

# ── Garde-fous PROD ──────────────────────────────────────────────────────────
if [[ "$TIER" == "prod" ]]; then
  if [[ "$SEED" -eq 1 ]]; then
    die "--seed est INTERDIT en prod : les données sont réelles. Le reseed n'existe que pour le tier dev."
  fi
  if [[ "$FORCE" -eq 1 && "$FORCE_UNSAFE" -ne 1 ]]; then
    die "--force seul est interdit en prod (il contournerait la santé de la cible). Utilisez --force-unsafe en connaissance de cause."
  fi

  # Garde données : la cible doit pointer la MÊME base que l'active.
  CURRENT_DB="$(jq -r --arg s "$CURRENT" '.stacks[$s].db.host // empty' "$REGISTRY")"
  TARGET_DB="$(jq -r --arg s "$TARGET" '.stacks[$s].db.host // empty' "$REGISTRY")"
  if [[ -z "$TARGET_DB" ]]; then
    log "Cible $TARGET sans db.host au registre → suppose la même base que $CURRENT (à confirmer à l'onboarding)."
  elif [[ "$TARGET_DB" != "$CURRENT_DB" ]]; then
    die "La cible $TARGET pointe une AUTRE base ($TARGET_DB ≠ $CURRENT_DB) : bascule refusée (données périmées). Le failover de base prod est db-standby-failover.yml, pas ce script."
  fi
fi

# ── 1. Santé de la cible AVANT bascule ───────────────────────────────────────
log "Sonde de la cible $TARGET ($BASE_URL/health)…"
http="$(curl --silent --output /dev/null --write-out '%{http_code}' --max-time 90 "$BASE_URL/health" || echo 000)"
if [[ "$http" != "200" ]]; then
  if [[ "$TIER" == "prod" && "$FORCE_UNSAFE" -eq 1 ]]; then
    warn "Cible malade (HTTP $http) — --force-unsafe accepté."
  elif [[ "$TIER" == "dev" && "$FORCE" -eq 1 ]]; then
    warn "Cible malade (HTTP $http) — --force accepté."
  else
    die "Cible malade (HTTP $http). Réveillez-la (cold start = réessayer dans 60 s) ou corrigez-la. Jamais de bascule vers une stack malade."
  fi
fi
log "Cible saine (HTTP $http)."

if [[ "$TIER" == "prod" ]]; then
  log "RAPPEL : les fronts (Vercel/Pages) et les verrous WEB_QUEUE_DRAIN/WEB_SCHEDULER_LOOP ne sont pas automatisés ici — checklist en fin de runbook."
fi

if [[ "$DRY" -eq 1 ]]; then
  if [[ "$TIER" == "prod" ]]; then
    log "[dry-run] Mettrait à jour : PROD_ACTIVE_STACK=$TARGET, PROD_API_BASE_URL=$BASE_URL${WEB_URL:+, PROD_WEB_URL=$WEB_URL}${ADMIN_URL:+, PROD_ADMIN_URL=$ADMIN_URL}, PROD_RENDER_SERVICE_URL"
  else
    log "[dry-run] Mettrait à jour : DEV_ACTIVE_STACK=$TARGET, DEV_API_BASE_URL=$BASE_URL${WEB_URL:+, DEV_WEB_URL=$WEB_URL}${ADMIN_URL:+, DEV_ADMIN_URL=$ADMIN_URL}"
    [[ "$SEED" -eq 1 ]] && log "[dry-run] Lancerait un reseed one-shot sur la cible."
  fi
  exit 0
fi

# ── 2. Pointeurs runtime (variables du dépôt) ────────────────────────────────
log "Mise à jour des variables du dépôt $REPO…"
if [[ "$TIER" == "prod" ]]; then
  gh variable set PROD_ACTIVE_STACK --body "$TARGET" --repo "$REPO"
  gh variable set PROD_API_BASE_URL --body "$BASE_URL" --repo "$REPO"
  [[ -n "$WEB_URL" ]]   && gh variable set PROD_WEB_URL   --body "$WEB_URL"   --repo "$REPO"
  [[ -n "$ADMIN_URL" ]] && gh variable set PROD_ADMIN_URL --body "$ADMIN_URL" --repo "$REPO"
  gh variable set PROD_RENDER_SERVICE_URL --body "${BASE_URL%/api/v1}" --repo "$REPO"
  log "Variables à jour : PROD_ACTIVE_STACK=$TARGET, PROD_API_BASE_URL=$BASE_URL"
else
  gh variable set DEV_ACTIVE_STACK --body "$TARGET" --repo "$REPO"
  gh variable set DEV_API_BASE_URL --body "$BASE_URL" --repo "$REPO"
  [[ -n "$WEB_URL" ]]   && gh variable set DEV_WEB_URL   --body "$WEB_URL"   --repo "$REPO"
  [[ -n "$ADMIN_URL" ]] && gh variable set DEV_ADMIN_URL --body "$ADMIN_URL" --repo "$REPO"
  log "Variables à jour : DEV_ACTIVE_STACK=$TARGET, DEV_API_BASE_URL=$BASE_URL"
fi

# ── 3. Reseed one-shot de la base cible (DEV uniquement) ─────────────────────
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

# ── 4. Vérification post-bascule ─────────────────────────────────────────────
code="$(curl --silent --output /dev/null --write-out '%{http_code}' --max-time 90 "$BASE_URL/health" || echo 000)"
[[ "$code" == "200" ]] || warn "Post-bascule : HTTP $code — vérifiez la stack $TARGET."

if [[ "$TIER" == "prod" ]]; then
  cat <<EOF

✅ Bascule PROD terminée : stack $CURRENT → $TARGET (API)

À finir dans l'ordre (docs/ops/PROD_STACK_ROTATION.md § Bascule) :
  1. Retirer WEB_QUEUE_DRAIN=false / WEB_SCHEDULER_LOOP=false sur le service
     Render de la cible (sinon : plus de queue ni de scheduler en prod).
  2. Basculer les fronts si la cible a ses propres projets Vercel/Pages :
     variables VERCEL_PROD_* / CLOUDFLARE_PROD_* vers ceux de la stack $TARGET,
     puis rejouer deploy-prod.yml (workflow_dispatch sur le tag courant).
  3. Le cas échéant, remettre WEB_QUEUE_DRAIN/WEB_SCHEDULER_LOOP à false sur
     l'ancienne stack (elle redevient standby).
  4. Aligner deploy/prod-stacks.json (active_stack, statuts) et commiter.
  5. Vérifier le smoke : /api/v1/health, login public, une page du web.

Rollback : scripts/stack-switch.sh $CURRENT --tier prod
EOF
else
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
fi
