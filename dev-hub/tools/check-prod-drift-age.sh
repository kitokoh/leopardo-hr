#!/usr/bin/env bash
# ============================================================
# check-prod-drift-age.sh — Alerte « prod derrière main > N h » (issue #8147)
# ------------------------------------------------------------
# Contexte (issue #8147, critère d'acceptation n°3) : la prod Render a tourné
# ~2 jours derrière `main` sans que personne ne le voie (#8092). La garde
# existante `deploy-drift-guard.yml` mesure déjà cet écart mais n'échoue qu'à
# 24 h (vars.PROD_DRIFT_MAX_AGE_HOURS). L'objectif de #8147 est une détection
# à **1 h** : au-delà, une correction livrée n'est pas en production et toute
# recette valide du code périmé.
#
# Ce script porte UNIQUEMENT la mesure et le verdict, pour être testable hors
# CI. Il ne détient aucun credential : la santé de la prod est publique, et
# l'API GitHub est appelée avec le token du runner.
#
# Sémantique de l'âge du retard : ancienneté du **plus vieux commit non
# déployé** (`commits[0]` du compare), pas celle du commit déployé. Un merge
# d'il y a 5 minutes sur une prod déployée il y a 5 jours n'a que 5 minutes de
# retard — mesurer l'âge du commit déployé produirait une alerte permanente et
# donc ignorée.
#
# Usage :
#   check-prod-drift-age.sh --url https://api.exemple.com \
#       [--max-age-hours 1] [--slack-webhook URL] [--fixture compare.json]
#
#   --fixture  lit un payload `GET /compare/{ref}...heads/main` depuis un
#              fichier au lieu d'appeler l'API (mode test / démonstration).
#              Combiné à --deployed-ref, permet de vérifier la logique d'âge
#              sans réseau.
#
# Codes de sortie : 0 = sous le seuil, 1 = au-dessus, 2 = mesure impossible.
# ============================================================
set -Eeuo pipefail

MAX_AGE_HOURS=1
TARGET_URL=""
SLACK_WEBHOOK=""
FIXTURE=""
DEPLOYED_REF=""

while [[ $# -gt 0 ]]; do
    case "$1" in
        --url) TARGET_URL="${2:-}"; shift 2 ;;
        --max-age-hours) MAX_AGE_HOURS="${2:-}"; shift 2 ;;
        --slack-webhook) SLACK_WEBHOOK="${2:-}"; shift 2 ;;
        --fixture) FIXTURE="${2:-}"; shift 2 ;;
        --deployed-ref) DEPLOYED_REF="${2:-}"; shift 2 ;;
        -h | --help) sed -n '2,32p' "${BASH_SOURCE[0]}"; exit 0 ;;
        *) echo "argument inconnu : $1" >&2; exit 2 ;;
    esac
done

if ! [[ "$MAX_AGE_HOURS" =~ ^[0-9]+$ ]]; then
    echo "::error::--max-age-hours doit être un entier positif (reçu : ${MAX_AGE_HOURS})" >&2
    exit 2
fi

notify() {
    local message="$1"
    echo "::error title=Prod dérive de main>::${message}"
    if [[ -n "$SLACK_WEBHOOK" ]]; then
        curl -fsS --max-time 10 -H 'Content-Type: application/json' \
            -d "$(jq -cn --arg text "🚨 [prod-drift] ${message}" '{text: $text}')" \
            "$SLACK_WEBHOOK" >/dev/null || echo "::warning::envoi Slack échoué (alerte non délivrée)"
    fi
}

# ── Référence servie par la prod ────────────────────────────────────────────
if [[ -n "$DEPLOYED_REF" ]]; then
    deployed="$DEPLOYED_REF"
elif [[ -n "$FIXTURE" ]]; then
    echo "::error::--fixture exige --deployed-ref (sinon la version servie n'est pas connue)" >&2
    exit 2
else
    if [[ -z "$TARGET_URL" ]]; then
        echo "::error::--url requis (ou --fixture + --deployed-ref)" >&2
        exit 2
    fi
    case "${TARGET_URL%/}" in
        */api/v1/health | */health) health_url="${TARGET_URL%/}" ;;
        */api/v1) health_url="${TARGET_URL%/}/health" ;;
        *) health_url="${TARGET_URL%/}/api/v1/health" ;;
    esac
    body="$(curl -fsS --max-time 45 --retry 2 --retry-delay 8 --retry-all-errors "$health_url")" || {
        echo "::warning::prod injoignable sur ${health_url} — âge du retard non mesurable ce run."
        exit 2
    }
    deployed="$(jq -r '.version // empty' <<<"$body")"
fi

if [[ -z "$deployed" ]]; then
    echo "::warning::version servie illisible — âge du retard non mesurable."
    exit 2
fi

# ── Payload de comparaison ──────────────────────────────────────────────────
if [[ -n "$FIXTURE" ]]; then
    compare="$(cat "$FIXTURE")"
else
    api="https://api.github.com/repos/${GITHUB_REPOSITORY:?GITHUB_REPOSITORY non défini}"
    compare=""
    # La prod publie une version de release (« 4.32.0 ») : tolérer le préfixe
    # « v » dans les deux sens, puis un SHA brut.
    for ref in "$deployed" "v${deployed}" "${deployed#v}"; do
        candidate="$(curl -fsS -H "Authorization: Bearer ${GH_TOKEN:?GH_TOKEN non défini}" \
            "${api}/compare/$(printf '%s' "$ref" | jq -sRr @uri)...heads/main" 2>/dev/null || true)"
        if [[ -n "$candidate" ]] && jq -e '.ahead_by' >/dev/null 2>&1 <<<"$candidate"; then
            compare="$candidate"
            break
        fi
    done
    if [[ -z "$compare" ]]; then
        echo "::warning::version servie « ${deployed} » introuvable comme ref/tag — écart non mesurable."
        exit 2
    fi
fi

gap="$(jq -r '.ahead_by // 0' <<<"$compare")"
oldest_pending="$(jq -r '.commits[0].commit.committer.date // empty' <<<"$compare")"

if [[ "$gap" == "0" ]]; then
    echo "prod↔main : aucun écart (version servie : ${deployed}). Rien à alerter."
    exit 0
fi

# Un écart non nul SANS date exploitable reste une anomalie : ne pas conclure
# silencieusement au vert (le but de la garde est de rendre le retard visible).
if [[ -z "$oldest_pending" ]]; then
    echo "::warning::${gap} commit(s) non déployé(s) mais date du plus vieux commit illisible — âge non calculable."
    exit 2
fi

lag_hours=$(( ( $(date -u +%s) - $(date -u -d "$oldest_pending" +%s) ) / 3600 ))

{
    echo "### Alerte drift prod ↔ main (#8147)"
    echo ""
    echo "| Mesure | Valeur |"
    echo "| --- | --- |"
    echo "| Version servie (prod) | \`${deployed}\` |"
    echo "| Commits de main non déployés | **${gap}** |"
    echo "| Plus vieux commit non déployé | ${oldest_pending} |"
    echo "| Âge du retard | **${lag_hours} h** (seuil ${MAX_AGE_HOURS} h) |"
} >> "${GITHUB_STEP_SUMMARY:-/dev/null}"

if (( lag_hours >= MAX_AGE_HOURS )); then
    notify "la prod est ${gap} commit(s) derrière main, retard âgé de ${lag_hours} h (seuil ${MAX_AGE_HOURS} h, version servie ${deployed}). Remédiation : secrets Render (#8397, docs/ops/RENDER_SECRETS_CHECKLIST.md) puis deploy-main-catchup — ou, si les tâches planifiées sont perdues au spin-down, services worker/scheduler dédiés (#8147)."
    exit 1
fi

echo "prod↔main : ${gap} commit(s) en attente, retard âgé de ${lag_hours} h < ${MAX_AGE_HOURS} h — sous le seuil, pas d'alerte."
exit 0
