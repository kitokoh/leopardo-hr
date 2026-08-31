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
# Usage : bash dev-hub/tools/check-actions-convention.sh [api_dir]
#
set -euo pipefail

API_DIR="${1:-api}"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "${ROOT}"

errors=0
warnings=0

mapfile -t ACTIONS < <(find "${API_DIR}/app/Modules" -path "*/Application/Actions/*.php" | sort)

if [[ ${#ACTIONS[@]} -eq 0 ]]; then
  echo "OK — aucune action dans Application/Actions/ (issue #6570)."
  exit 0
fi

for f in "${ACTIONS[@]}"; do
  rel="${f#${API_DIR}/}"
  base="$(basename "${f}" .php)"

  if ! grep -q "function execute(" "${f}"; then
    echo "::error::Action sans execute() : ${rel} → 1 action = 1 execute() (issue #6570)." >&2
    errors=$((errors + 1))
  fi

  # Un Service/Job résiduel n'a PAS de execute() (les Actions en ont toutes une).
  # NB : PostJob est une Action légitime (verbe+objet) — le suffixe seul ne suffit pas.
  if [[ "${base}" == *Service || "${base}" == *Job ]] && ! grep -q "function execute(" "${f}"; then
    echo "::error::Service/Job dans Application/Actions/ : ${rel} → Infrastructure/{Services,Jobs}/ (issue #6570)." >&2
    errors=$((errors + 1))
  fi

  if [[ "${base}" != *Action ]]; then
    echo "::warning::Action sans suffixe 'Action' : ${rel} (renommage en lot dédié, issue #6570)." >&2
    warnings=$((warnings + 1))
  fi
done

if [[ "${errors}" -gt 0 ]]; then
  echo "::error::Convention Actions : ${errors} erreur(s) bloquante(s) (issue #6570)." >&2
  exit 1
fi

echo "OK — ${#ACTIONS[@]} actions conformes execute()/placement (${warnings} sans suffixe Action, lot séparé)."
