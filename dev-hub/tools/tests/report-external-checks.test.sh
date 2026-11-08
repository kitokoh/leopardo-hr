#!/usr/bin/env bash
# Garde #9189 — le rapport « état des checks » ne concerne qu'une PR OUVERTE.
#
# Constat (2026-11-08) : le workflow `pr-external-checks-report.yml` se
# déclenchait sur `workflow_run` sans regarder l'événement du run source. Sur
# `main`, il partait donc ~22 fois par commit pour résoudre le SHA en PR
# **fusionnée** et rafraîchir le commentaire d'une PR fermée.
#
# Cette garde n'utilise AUCUN réseau : un faux `curl` répond depuis des
# fixtures et journalise chaque appel. Elle est ROUGE sur le script d'avant le
# correctif (le commentaire est posté sur la PR fermée #9187) et VERTE après.
#
# Usage : dev-hub/tools/tests/report-external-checks.test.sh
#         SCRIPT=/chemin/vers/script.sh dev-hub/tools/tests/report-external-checks.test.sh

set -uo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"
SCRIPT="${SCRIPT:-${ROOT}/dev-hub/tools/report-external-checks.sh}"

if [[ ! -f "${SCRIPT}" ]]; then
  echo "❌ script introuvable : ${SCRIPT}"
  exit 1
fi

FAKE_BIN="$(mktemp -d)"
CALLS="$(mktemp)"
trap 'rm -rf "${FAKE_BIN}" "${CALLS}"' EXIT

# Faux `curl` : la dernière position est l'URL, le reste (dont `-X POST`) est
# journalisé. Toute URL non reconnue rend `{}` — un script qui interroge une
# route inattendue produit alors une sortie vide, pas un faux vert silencieux.
cat > "${FAKE_BIN}/curl" <<'CURL'
#!/usr/bin/env bash
printf '%s\n' "$*" >> "${CURL_CALLS}"
url="${!#}"
case "${url}" in
  */commits/*/pulls)
    printf '%s' '[{"number":9187,"state":"closed","base":{"ref":"main"}}]' ;;
  */pulls/9187)
    printf '%s' '{"state":"closed","merged_at":"2026-11-08T03:37:46Z","head":{"sha":"deadbeefdeadbeef"}}' ;;
  */pulls/4242)
    printf '%s' '{"state":"open","merged_at":null,"head":{"sha":"cafe1234cafe1234"}}' ;;
  */commits/cafe1234cafe1234/check-runs*)
    printf '%s' '{"check_runs":[{"name":"actionlint (+ shellcheck)","conclusion":"success","status":"completed"},{"name":"Tests - Leopardo","conclusion":"failure","status":"completed"}]}' ;;
  */issues/4242/comments\?per_page=100)
    printf '%s' '[]' ;;
  */issues/4242/comments)
    printf '%s' '{"id":777}' ;;
  *)
    printf '%s' '{}' ;;
esac
CURL
chmod +x "${FAKE_BIN}/curl"

failures=0
run() {
  PATH="${FAKE_BIN}:${PATH}" CURL_CALLS="${CALLS}" GH_TOKEN="fake-token" \
    bash "${SCRIPT}" "$@" 2>&1
}

# --- 1. Le défaut #9189 : un SHA de main -> PR FUSIONNÉE (#9187) ------------
: > "${CALLS}"
out="$(run deadbeefdeadbeef --comment)"; rc=$?
if [[ "${rc}" -ne 0 ]]; then
  echo "❌ [PR fermée] exit ${rc} (attendu 0 — c'est un rapport, pas un gate)"
  failures=$((failures + 1))
fi
if ! grep -q "n'est pas ouverte" <<<"${out}"; then
  echo "❌ [PR fermée] le script n'explique pas qu'il refuse une PR fermée : ${out}"
  failures=$((failures + 1))
fi
if grep -q -- "issues/9187/comments" "${CALLS}"; then
  echo "❌ [PR fermée] un commentaire a été publié/mis à jour sur la PR fermée #9187"
  failures=$((failures + 1))
fi
if [[ "${failures}" -eq 0 ]]; then
  echo "✅ [PR fermée] SHA de main -> PR #9187 close : aucun commentaire touché"
fi

# --- 2. Le chemin nominal ne doit PAS être cassé : PR ouverte #4242 ---------
before="${failures}"
: > "${CALLS}"
out="$(run 4242 --comment)"; rc=$?
if [[ "${rc}" -ne 0 ]]; then
  echo "❌ [PR ouverte] exit ${rc} (attendu 0)"
  failures=$((failures + 1))
fi
if ! grep -q -- "Rapport publié sur #4242" <<<"${out}"; then
  echo "❌ [PR ouverte] le rapport n'a pas été publié : ${out}"
  failures=$((failures + 1))
fi
if ! grep -q -- "-X POST" "${CALLS}"; then
  echo "❌ [PR ouverte] aucun POST de commentaire (le chemin nominal est cassé)"
  failures=$((failures + 1))
fi
if [[ "${failures}" -eq "${before}" ]]; then
  echo "✅ [PR ouverte] PR #4242 ouverte : rapport publié, classement des checks intact"
fi

if [[ "${failures}" -gt 0 ]]; then
  echo "❌ report-external-checks.test.sh : ${failures} problème(s) (#9189)"
  exit 1
fi

echo "✅ report-external-checks.test.sh : PR fermée ignorée, PR ouverte rapportée (#9189)"
