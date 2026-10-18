#!/usr/bin/env bash
# release-drift-check.sh — Vérifie que le tag publié correspond au code RÉELLEMENT servi (issue #8700).
#
# Problème mesuré le 2026-10-18 : la prod servait la version `4.33.0` (tag du
# 2026-09-20) alors que `main` avait avancé de plusieurs dizaines de commits.
# Rien dans le dépôt ne reliait « tag publié » à « code en ligne » : une release
# qui échoue (quota Vercel, secret absent, healthcheck rouge) laissait la prod
# silencieusement figée. Ce script transforme ce silence en verdict.
#
# Ce qu'il compare :
#   1. le dernier tag `vX.Y.Z` poussé (ou `--tag` explicite) ;
#   2. le SHA que l'API prod annonce réellement (`/api/v1/health` → `version`) ;
#   3. le SHA du commit du tag.
#
# ⚠️ L'API répond sur 7 caractères (`substr(RENDER_GIT_COMMIT, 0, 7)`) alors que
# le tag est un SHA de 40 : la comparaison est donc faite sur le préfixe, et le
# script le dit explicitement (`match_basis`). Comparer 7 à 40 caractères
# produirait un faux positif permanent — c'est le piège que ce script évite.
#
# Codes de sortie :
#   0 — prod sert exactement le tag
#   1 — drift : prod sert un autre commit que le tag
#   2 — indéterminé : API prod injoignable, ou réponse sans champ `version`
#       (un service suspendu Render répond une page HTML 503 : ce n'est PAS un
#       drift, c'est une panne, et la distinction change l'action à mener)
#
# Usage :
#   scripts/release-drift-check.sh                 # dernier tag du dépôt
#   scripts/release-drift-check.sh --tag v4.33.0
#   scripts/release-drift-check.sh --api-url https://leopardo-prod.onrender.com/api/v1
#   scripts/release-drift-check.sh --json          # sortie machine (CI)
#
# Aucun secret requis : la sonde est publique et en lecture seule.

set -euo pipefail

API_URL="${PROD_API_BASE_URL:-https://leopardo-prod.onrender.com/api/v1}"
TAG=""
JSON=0
TIMEOUT=30

usage() {
    sed -n '2,40p' "$0" | sed 's/^# \{0,1\}//'
    exit 2
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        --tag) TAG="${2:-}"; shift 2 ;;
        --api-url) API_URL="${2:-}"; shift 2 ;;
        --json) JSON=1; shift ;;
        --timeout) TIMEOUT="${2:-}"; shift 2 ;;
        -h|--help) usage ;;
        *) echo "Option inconnue : $1" >&2; usage ;;
    esac
done

if [[ -z "$TAG" ]]; then
    TAG="$(git tag --list 'v*' --sort=-v:refname | head -n 1 || true)"
    if [[ -z "$TAG" ]]; then
        echo "Aucun tag vX.Y.Z dans ce clone (fetch --tags ?)" >&2
        exit 2
    fi
fi

if ! [[ "$TAG" =~ ^v[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
    echo "Tag invalide : ${TAG} (attendu vX.Y.Z)" >&2
    exit 2
fi

TAG_COMMIT="$(git rev-parse "${TAG}^{commit}" 2>/dev/null || true)"
if [[ -z "$TAG_COMMIT" ]]; then
    echo "Tag introuvable localement : ${TAG} — lancer 'git fetch --tags'" >&2
    exit 2
fi

# Sonde l'API. `-w '%{http_code}'` séparé du corps : on veut distinguer
# « injoignable » (000), « suspendu/erreur » (5xx) et « réponse exploitable ».
BODY_FILE="$(mktemp)"
trap 'rm -f "$BODY_FILE"' EXIT
HTTP_CODE="$(curl -s -o "$BODY_FILE" -w '%{http_code}' -m "$TIMEOUT" "${API_URL%/}/health" || echo '000')"

LIVE_VERSION=""
if [[ -s "$BODY_FILE" ]]; then
    LIVE_VERSION="$(python3 - "$BODY_FILE" <<'PY' 2>/dev/null || true
import json, sys
try:
    with open(sys.argv[1], encoding="utf-8", errors="replace") as fh:
        payload = json.load(fh)
except Exception:
    sys.exit(0)
value = payload.get("version") if isinstance(payload, dict) else None
if isinstance(value, str):
    print(value.strip())
PY
)"
fi

STATUS="unknown"
EXIT_CODE=2
REASON=""

if [[ "$HTTP_CODE" == "000" ]]; then
    REASON="API prod injoignable (DNS/réseau/timeout ${TIMEOUT}s)"
elif [[ "$HTTP_CODE" != "200" ]]; then
    REASON="API prod a répondu HTTP ${HTTP_CODE} (service suspendu ou en erreur, pas un drift de release)"
elif [[ -z "$LIVE_VERSION" ]]; then
    REASON="health 200 mais aucun champ 'version' exploitable"
else
    # Deux formes possibles de `version` selon la façon dont le service a été
    # déployé — il faut gérer LES DEUX, sinon le script crie au drift à tort :
    #   a) `APP_VERSION` épinglé au tag par deploy-prod.yml  → « 4.33.0 »
    #   b) aucun APP_VERSION → `RENDER_GIT_COMMIT` tronqué → « 407cef0 »
    # Mesuré le 2026-10-18 : la prod renvoie « 4.33.0 » alors que c'est bien le
    # tag v4.33.0 qui tourne. Comparer au SHA aurait produit un faux positif.
    if [[ "$LIVE_VERSION" == "${TAG#v}" ]]; then
        STATUS="match"
        EXIT_CODE=0
        MATCH_BASIS="app_version_tag"
    elif [[ "$TAG_COMMIT" == "$LIVE_VERSION"* ]]; then
        STATUS="match"
        EXIT_CODE=0
        MATCH_BASIS="commit_prefix"
    elif [[ "$LIVE_VERSION" == "${TAG_COMMIT:0:${#LIVE_VERSION}}" ]]; then
        STATUS="match"
        EXIT_CODE=0
        MATCH_BASIS="commit_prefix"
    else
        STATUS="drift"
        EXIT_CODE=1
        MATCH_BASIS="none"
        REASON="prod sert ${LIVE_VERSION}, tag ${TAG} pointe sur ${TAG_COMMIT:0:7}"
    fi
fi

[[ -z "${MATCH_BASIS:-}" ]] && MATCH_BASIS="none"

if [[ "$JSON" -eq 1 ]]; then
    TAG_SHORT="${TAG_COMMIT:0:7}"
    REASON_JSON="$(printf '%s' "$REASON" | python3 -c 'import json,sys; print(json.dumps(sys.stdin.read()))')"
    cat <<JSON
{"status":"${STATUS}","tag":"${TAG}","tag_commit":"${TAG_COMMIT}","tag_commit_short":"${TAG_SHORT}","live_version":"${LIVE_VERSION}","api_url":"${API_URL%/}","http_code":"${HTTP_CODE}","match_basis":"${MATCH_BASIS}","reason":${REASON_JSON},"exit_code":${EXIT_CODE}}
JSON
else
    echo "API prod      : ${API_URL%/}/health"
    echo "Tag de référence : ${TAG} (${TAG_COMMIT:0:7})"
    echo "Version servie  : ${LIVE_VERSION:-<aucune>} (HTTP ${HTTP_CODE})"
    case "$STATUS" in
        match) echo "Verdict       : OK — la prod sert bien le code du tag." ;;
        drift) echo "Verdict       : DRIFT — ${REASON}" ;;
        *)     echo "Verdict       : INDÉTERMINÉ — ${REASON}" ;;
    esac
fi

exit "$EXIT_CODE"
