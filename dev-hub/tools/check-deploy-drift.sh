#!/usr/bin/env bash
# ============================================================
# check-deploy-drift.sh — Garde anti-dérive « environnement déployé ↔ main »
#                        (issue #7304)
# ------------------------------------------------------------
# Constat (audit onboarding 2026-09-13, issue #7304) : l'environnement dev
# Render (`gestionemployerbackend`) servait un commit ~50 PR en retard sur
# `main`. Toute campagne QA menée dessus validait du code périmé — d'où de
# faux bugs (« l'assistant d'onboarding se réouvre ») et de vrais correctifs
# invérifiables. Rien dans le dépôt ne comparait la version *déployée* à la
# version *attendue* : ce script est ce comparateur.
#
# Usage :
#   dev-hub/tools/check-deploy-drift.sh --url <url-sante> [options]
#
#   --url URL           URL de base de l'API ou URL complète .../health
#                       (défaut : $DEV_API_BASE_URL, sinon $DEPLOY_DRIFT_URL)
#   --expect REF        SHA (court/long), ref git, ou version de release
#                       (ex. « v4.32.0 » ou « 4.32.0 » — le préfixe « v » est
#                       toléré) ; défaut : auto → origin/main, sinon HEAD
#   --label NOM         étiquette affichée dans le rapport (défaut : « api »)
#   --allow-release     ne pas échouer si /health expose une version de
#                       release (ex. 4.31.0) et non un SHA de commit : la
#                       corrélation commit ↔ version est déclarée impossible
#   --max-failed N      avertit (::warning::) si failed_jobs > N (défaut 10,
#                       0 = désactive)
#   --strict-failed     transforme l'avertissement failed_jobs en échec
#   --timeout S         délai HTTP en secondes (défaut 30)
#   -h | --help
#
# Sortie : 0 = aligné (ou incomparable assumé), 1 = DÉRIVE détectée,
#          2 = erreur technique (URL injoignable, réponse illisible).
#
# Exemple (ce que fait la garde CI `deploy-drift-guard.yml`) :
#   dev-hub/tools/check-deploy-drift.sh \
#     --url "$DEV_API_BASE_URL" --expect origin/main --label dev
# ============================================================
set -uo pipefail

URL="${DEV_API_BASE_URL:-${DEPLOY_DRIFT_URL:-}}"
EXPECT="auto"
LABEL="api"
ALLOW_RELEASE=0
MAX_FAILED=10
STRICT_FAILED=0
TIMEOUT=30

usage() {
  sed -n '2,40p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --url) URL="${2:-}"; shift 2 ;;
    --expect) EXPECT="${2:-}"; shift 2 ;;
    --label) LABEL="${2:-}"; shift 2 ;;
    --allow-release) ALLOW_RELEASE=1; shift ;;
    --max-failed) MAX_FAILED="${2:-10}"; shift 2 ;;
    --strict-failed) STRICT_FAILED=1; shift ;;
    --timeout) TIMEOUT="${2:-30}"; shift 2 ;;
    -h|--help) usage; exit 0 ;;
    *) echo "::error::option inconnue : $1 (voir --help)" >&2; exit 2 ;;
  esac
done

if [[ -z "${URL}" ]]; then
  echo "::error::aucune URL fournie : passer --url, ou définir \$DEV_API_BASE_URL / \$DEPLOY_DRIFT_URL (issue #7304)." >&2
  exit 2
fi

# URL de base -> endpoint /health. Les variables de dépôt réelles portent des
# formes différentes (`.../api/v1` pour DEV_API_BASE_URL, `...` tout court
# ailleurs) : normaliser explicitement plutôt que d'empiler les segments
# (l'empilement produisait `.../api/v1/api/v1/health` → 404, mesuré sur dev).
URL="${URL%/}"
case "${URL}" in
  */api/v1/health | */health) ;;                 # déjà l'endpoint
  */api/v1) URL="${URL}/health" ;;               # base versionnée
  *) URL="${URL}/api/v1/health" ;;               # hôte nu
esac

# Version attendue : un SHA de commit, résolu localement si on demande « auto ».
if [[ "${EXPECT}" == "auto" ]]; then
  if git rev-parse --verify --quiet origin/main >/dev/null 2>&1; then
    EXPECT="$(git rev-parse origin/main)"
  elif git rev-parse --verify --quiet HEAD >/dev/null 2>&1; then
    EXPECT="$(git rev-parse HEAD)"
  else
    echo "::error::impossible de résoudre origin/main ou HEAD — passer --expect <sha> (issue #7304)." >&2
    exit 2
  fi
elif [[ "${EXPECT}" =~ ^v?[0-9]+\.[0-9]+ ]]; then
  # Version de release (« v4.32.0 » / « 4.32.0 ») : à comparer **comme une
  # version**, jamais comme une ref git — sinon `git rev-parse` la résout en
  # commit de tag et la comparaison porte sur le mauvais objet (constaté :
  # `--expect v4.32.0` devenait `2db0baa`).
  :
elif git rev-parse --verify --quiet "${EXPECT}" >/dev/null 2>&1; then
  EXPECT="$(git rev-parse "${EXPECT}")"
fi
EXPECT="$(echo "${EXPECT}" | tr '[:upper:]' '[:lower:]')"
EXPECT_SHORT="${EXPECT:0:7}"

PAYLOAD="$(curl -fsS --max-time "${TIMEOUT}" "${URL}" 2>/dev/null)" || {
  echo "::error::[${LABEL}] ${URL} injoignable ou non-2xx (issue #7304 : un environnement muet ne peut pas être qualifié d'aligné)." >&2
  exit 2
}

_version() { printf '%s' "${PAYLOAD}" | sed -n 's/.*"version"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/p' | head -n 1; }
_number()  { printf '%s' "${PAYLOAD}" | sed -n "s/.*\"${1}\"[[:space:]]*:[[:space:]]*\([0-9]\+\).*/\1/p" | head -n 1; }

DEPLOYED="$(_version)"
DEPLOYED_LC="$(echo "${DEPLOYED}" | tr '[:upper:]' '[:lower:]')"
FAILED="$(_number failed_jobs)"
QUEUE_SIZE="$(_number size)"

echo "=== Garde anti-dérive déploiement (issue #7304) — ${LABEL} ==="
echo "  URL            : ${URL}"
echo "  Attendu        : ${EXPECT}" 
echo "  Déployé        : ${DEPLOYED:-<absent>}"
[[ -n "${QUEUE_SIZE}" ]] && echo "  Queue          : ${QUEUE_SIZE} job(s) en attente, ${FAILED:-0} échec(s)"

if [[ -z "${DEPLOYED}" ]]; then
  echo "::error::[${LABEL}] /health ne publie pas de champ « version » : impossible de corréler le déploiement (issue #7304)." >&2
  exit 2
fi

STATUS=0
# Normalisation commune : insensible à la casse, préfixe « v » de tag toléré
# (« v4.32.0 » et « 4.32.0 » sont la même release).
_norm() { printf '%s' "$1" | tr '[:upper:]' '[:lower:]' | sed 's/^v//'; }
EXPECT_NORM="$(_norm "${EXPECT}")"
DEPLOYED_NORM="$(_norm "${DEPLOYED}")"
is_version() { [[ "$1" =~ ^[0-9]+\.[0-9]+ ]]; }

if [[ "${DEPLOYED_NORM}" == "${EXPECT_NORM}" || "${DEPLOYED_LC}" == "${EXPECT_SHORT}" || "${EXPECT}" == "${DEPLOYED_LC}"* ]]; then
  # Aligné : SHA exact/abrégé (Render publie un SHA court via RENDER_GIT_COMMIT)
  # ou version de release identique (tag « v4.32.0 » ↔ APP_VERSION « 4.32.0 »).
  echo "  ✅ Aligné sur ${EXPECT_NORM}."
elif is_version "${EXPECT_NORM}" && is_version "${DEPLOYED_NORM}"; then
  # Deux versions de release différentes : une Release n'a pas été déployée.
  echo "::error::[${LABEL}] DÉRIVE : ${URL} sert la release « ${DEPLOYED} » alors que « ${EXPECT} » est attendue — la Release n'a pas été déployée (issue #7304)." >&2
  STATUS=1
elif is_version "${DEPLOYED_NORM}" && ! is_version "${EXPECT_NORM}"; then
  # L'environnement publie une version de release sans que l'appelant en ait
  # fourni une : la corrélation par SHA est impossible par construction.
  if [[ "${ALLOW_RELEASE}" == "1" ]]; then
    echo "  ℹ️  Version de release « ${DEPLOYED} » : comparaison par SHA impossible (--allow-release) — pour un contrôle réel, passer --expect vX.Y.Z (dernier tag)."
  else
    echo "::error::[${LABEL}] version de release « ${DEPLOYED} » au lieu d'un SHA : passer --expect vX.Y.Z (contrôle réel) ou --allow-release (incomparable assumé) — issue #7304." >&2
    STATUS=1
  fi
else
  echo "::error::[${LABEL}] DÉRIVE : ${URL} sert « ${DEPLOYED} » alors que ${EXPECT_SHORT} est attendu — l'environnement ne sert pas le code attendu (issue #7304)."
  echo "::error::[${LABEL}] corrigez : Render → service → Manual Deploy, puis revérifiez ce script."
  STATUS=1
fi

if [[ -n "${FAILED}" && "${MAX_FAILED}" -gt 0 && "${FAILED}" -gt "${MAX_FAILED}" ]]; then
  if [[ "${STRICT_FAILED}" == "1" ]]; then
    echo "::error::[${LABEL}] ${FAILED} jobs en échec (> ${MAX_FAILED}) — aucun worker ne draine la queue (issue #7304)." >&2
    STATUS=1
  else
    echo "::warning::[${LABEL}] ${FAILED} jobs en échec (> ${MAX_FAILED}) — aucun worker ne draine la queue de cet environnement (issue #7304)."
  fi
fi

exit "${STATUS}"
