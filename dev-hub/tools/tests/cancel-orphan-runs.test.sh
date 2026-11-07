#!/usr/bin/env bash
# cancel-orphan-runs.test.sh — garde de dev-hub/tools/cancel-orphan-runs.sh
#
# Constat 2026-11-07 (suite de #9160) : le workflow `cleanup-orphan-runs.yml`
# se déclenche à la fermeture d'une PR. Son propre run porte donc la branche
# qui vient d'être supprimée : le script le classait « orphelin » et
# s'annulait LUI-MÊME. Résultat visible, check « Cancel runs on deleted
# branches » en `cancelled` sur chaque PR mergée (`gh pr checks` affiche
# `cancelled` comme rouge) alors que le nettoyage avait bien agi.
#
# Ce test verrouille trois propriétés, sans réseau :
#   1. le run courant (GITHUB_RUN_ID) n'est jamais annulé ;
#   2. un run d'une branche vivante n'est jamais annulé ;
#   3. une lecture de branches VIDE (API en échec) n'annule rien et échoue
#      (fail-closed) — une lecture vide rendt toutes les branches « mortes ».
#
# Usage : bash dev-hub/tools/tests/cancel-orphan-runs.test.sh
# Dépendances : bash, jq (comme le script testé).

set -uo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
TOOL="${SCRIPT_DIR}/../cancel-orphan-runs.sh"

if ! command -v jq >/dev/null 2>&1; then
  echo "SKIP: jq introuvable (le script testé en dépend)."
  exit 0
fi

if [[ ! -f "${TOOL}" ]]; then
  echo "❌ outil introuvable : ${TOOL}" >&2
  exit 1
fi

TMP_DIR="$(mktemp -d)"
trap 'rm -rf "${TMP_DIR}"' EXIT

FAILURES=0
fail() { echo "❌ $1" >&2; FAILURES=$((FAILURES + 1)); }

# ── Faux `gh` : répond aux 4 lectures utilisées par le script ────────────────
# BRANCHES_JSON / RUNS_JSON sont injectés par chaque scénario.
mkdir -p "${TMP_DIR}/bin"
cat > "${TMP_DIR}/bin/gh" <<'FAKE_GH'
#!/usr/bin/env bash
set -uo pipefail
endpoint=""
post=0
prev=""
for arg in "$@"; do
  [[ "${prev}" == "-X" && "${arg}" == "POST" ]] && post=1
  [[ "${arg}" == repos/* ]] && endpoint="${arg}"
  prev="${arg}"
done
if [[ "${post}" -eq 1 ]]; then
  printf '%s\n' "${endpoint}" >> "${CANCEL_LOG}"
  exit 0
fi
case "${endpoint}" in
  repos/o/r) echo '{"default_branch":"main"}' ;;
  repos/o/r/branches*) printf '%s\n' "${BRANCHES_JSON}" ;;
  repos/o/r/pulls*) echo '[]' ;;
  repos/o/r/actions/runs?status=queued*) echo '{"workflow_runs":[]}' ;;
  repos/o/r/actions/runs?status=pending*) echo '{"workflow_runs":[]}' ;;
  repos/o/r/actions/runs?status=in_progress*) printf '%s\n' "${RUNS_JSON}" ;;
  *) echo '[]' ;;
esac
FAKE_GH
chmod +x "${TMP_DIR}/bin/gh"

# `run_tool` est appelé dans une substitution de commande : les variables
# exportées DANS la fonction seraient perdues pour le reste du test.
CANCEL_LOG="${TMP_DIR}/cancel.log"
export CANCEL_LOG

run_tool() { # <branches_json> <runs_json> [self_run_id]
  local branches="$1" runs="$2" self_id="${3:-}"
  : > "${CANCEL_LOG}"
  export BRANCHES_JSON="${branches}"
  export RUNS_JSON="${runs}"
  export PATH="${TMP_DIR}/bin:${PATH}"
  export GH_TOKEN="fake-token"
  GITHUB_RUN_ID="${self_id}" bash "${TOOL}" o/r > "${TMP_DIR}/out.log" 2>&1
  echo "$?"
}

cancelled_ids() { grep -oE 'runs/[0-9]+/cancel' "${CANCEL_LOG}" 2>/dev/null | grep -oE '[0-9]+' | sort | tr '\n' ' '; }

# ── Scénario 1 : run courant + branche vivante préservés, orphelin annulé ────
branches='[{"name":"main"},{"name":"fix/live"}]'
runs='{"workflow_runs":[{"id":2002,"head_branch":"fix/live"},{"id":2003,"head_branch":"fix/ghost"},{"id":4242,"head_branch":"fix/ghost"}]}'
code="$(run_tool "${branches}" "${runs}" 4242)"
ids="$(cancelled_ids)"

[[ "${code}" == "0" ]] || fail "scénario 1 : exit ${code} (attendu 0) — $(tail -3 "${TMP_DIR}/out.log")"
[[ "${ids}" != *"2002"* ]] || fail "scénario 1 : run d'une branche VIVANTE annulé (2002)"
[[ "${ids}" != *"4242"* ]] || fail "scénario 1 : run COURANT annulé (4242) — c'est le défaut du 2026-11-07"
[[ "${ids}" == *"2003"* ]] || fail "scénario 1 : run d'une branche morte NON annulé (2003) — ids annulés : ${ids}"

# ── Scénario 2 : lecture de branches vide → fail-closed, aucune annulation ───
code="$(run_tool '[]' "${runs}" 4242)"
ids="$(cancelled_ids)"

[[ "${code}" != "0" ]] || fail "scénario 2 : exit 0 sur une lecture de branches vide (attendu != 0)"
[[ -z "${ids}" ]] || fail "scénario 2 : annulations sur lecture vide (${ids})"
grep -q "fail-closed" "${TMP_DIR}/out.log" || fail "scénario 2 : message fail-closed absent"

if [[ "${FAILURES}" -gt 0 ]]; then
  echo "❌ cancel-orphan-runs.test.sh : ${FAILURES} écart(s)." >&2
  exit 1
fi

echo "✅ cancel-orphan-runs.test.sh : run courant épargné, branches vivantes protégées, lecture vide fail-closed."
