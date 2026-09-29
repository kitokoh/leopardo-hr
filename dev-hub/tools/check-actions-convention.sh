#!/usr/bin/env bash
#
# check-actions-convention.sh — garde CI « couche Application/Actions saine »
# (issue #6570, audit DDD M2 2026-08-31).
#
# Convention : « 1 action = 1 execute() », nom verbe+objet suffixé `Action`,
# et AUCUN Service/Job dans Application/Actions/ (ils vivent sous
# Infrastructure/{Services,Jobs}/).
#
# - BLOQUANT : fichier sans execute() ; fichier *Service.php / *Job.php.
# - WARNING  : fichier sans suffixe `Action` (dette historique, renommage en
#   lot dédié — les NOUVELLES actions doivent suivre la convention).
#
# Dette connue (câblage #8239, 2026-09-29) : la garde ne doit échouer que sur
# les violations NOUVELLES. Les 27 erreurs préexistantes (26 fichiers, mesurées
# sur main @ 2026-09-29) sont listées dans check-actions-convention-allowlist.txt.
# - Un fichier allowlisté qui viole encore : ::notice (dette tracée), pas d'échec.
# - STALENESS STRICT (doctrine #3596) : une entrée allowlistée dont le fichier
#   est corrigé ou supprimé = ÉCHEC → purger la ligne (la liste ne doit que
#   rétrécir, jamais s'agrandir sans discussion documentée).
# - La résorption de la dette se fait module par module (issues filles), pas
#   en élargissant cette liste.
#
# Usage : bash dev-hub/tools/check-actions-convention.sh [api_dir]
#
set -euo pipefail

API_DIR="${1:-api}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
cd "${ROOT}"

ALLOWLIST="${SCRIPT_DIR}/check-actions-convention-allowlist.txt"

errors=0
warnings=0

declare -A KNOWN=()
if [[ -f "${ALLOWLIST}" ]]; then
  while IFS= read -r line; do
    line="${line%%#*}"; line="$(echo "${line}" | xargs)"
    [[ -z "${line}" ]] && continue
    KNOWN["${line}"]=0
  done < "${ALLOWLIST}"
fi

mapfile -t ACTIONS < <(find "${API_DIR}/app/Modules" -path "*/Application/Actions/*.php" | sort)

if [[ ${#ACTIONS[@]} -eq 0 ]]; then
  echo "OK — aucune action dans Application/Actions/ (issue #6570)."
  exit 0
fi

report_error() {
  local rel="$1"; shift
  local msg="$*"
  if [[ -v "KNOWN[${rel}]" ]]; then
    echo "::notice::Violation connue (allowlist #8239) : ${rel} — ${msg}"
    KNOWN["${rel}"]=1
  else
    echo "::error::${msg} : ${rel} (issue #6570 — nouvelle violation, non allowlistée)." >&2
    errors=$((errors + 1))
  fi
}

for f in "${ACTIONS[@]}"; do
  rel="${f#${API_DIR}/}"
  base="$(basename "${f}" .php)"

  if ! grep -q "function execute(" "${f}"; then
    report_error "${rel}" "Action sans execute() → 1 action = 1 execute()"
  fi

  # Un Service/Job résiduel n'a PAS de execute() (les Actions en ont toutes une).
  # NB : PostJob est une Action légitime (verbe+objet) — le suffixe seul ne suffit pas.
  if [[ "${base}" == *Service || "${base}" == *Job ]] && ! grep -q "function execute(" "${f}"; then
    report_error "${rel}" "Service/Job dans Application/Actions/ → Infrastructure/{Services,Jobs}/"
  fi

  if [[ "${base}" != *Action ]]; then
    echo "::warning::Action sans suffixe 'Action' : ${rel} (renommage en lot dédié, issue #6570)."
    warnings=$((warnings + 1))
  fi
done

# Staleness strict : toute entrée allowlistée qui n'a produit AUCUNE violation
# (fichier corrigé ou supprimé) doit être purgée — la liste ne fait que rétrécir.
stale=0
for entry in "${!KNOWN[@]}"; do
  if [[ "${KNOWN[${entry}]}" -eq 0 ]]; then
    echo "::error::Entrée allowlist obsolète : ${entry} — fichier corrigé ou supprimé, purger la ligne de check-actions-convention-allowlist.txt (doctrine #3596)." >&2
    stale=$((stale + 1))
  fi
done

if [[ "${errors}" -gt 0 || "${stale}" -gt 0 ]]; then
  echo "::error::Convention Actions : ${errors} nouvelle(s) violation(s), ${stale} entrée(s) allowlist obsolète(s) (issues #6570/#8239)." >&2
  exit 1
fi

known_count=0
for entry in "${!KNOWN[@]}"; do [[ "${KNOWN[${entry}]}" -eq 1 ]] && known_count=$((known_count + 1)); done
echo "OK — ${#ACTIONS[@]} actions conformes execute()/placement (${known_count} violation(s) connue(s) allowlistée(s) #8239, ${warnings} sans suffixe Action, lot séparé)."
