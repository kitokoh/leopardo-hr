#!/usr/bin/env bash
#
# check-application-layer-placement.sh — garde CI « couche Application saine »
# (issue #6571, audit DDD M3 2026-08-31).
#
# Les Services et Jobs d'un module vivent sous Infrastructure/{Services,Jobs}/
# (convention réelle du repo, cf. api/ARCHITECTURE.md §Conventions). La couche
# Application ne contient que des Actions (1 action = 1 execute()) et des DTOs.
# Un Service/Job posé dans Application/ est une erreur de placement → CI rouge.
#
# Usage : bash dev-hub/tools/check-application-layer-placement.sh [api_dir]
#
set -euo pipefail

API_DIR="${1:-api}"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "${ROOT}"

ALLOWLIST="dev-hub/tools/application-layer-placement-allowlist.txt"
violations=0

mapfile -t OFFENDERS < <(find "${API_DIR}/app/Modules" -type f \( -path "*/Application/Services/*" -o -path "*/Application/Jobs/*" \) -name "*.php" | sort)

if [[ ${#OFFENDERS[@]} -eq 0 ]]; then
  echo "OK — aucun Service/Job dans Application/ (issue #6571)."
  exit 0
fi

for f in "${OFFENDERS[@]}"; do
  rel="${f#${API_DIR}/}"
  if [[ -f "${ALLOWLIST}" ]] && grep -Fqx "${rel}" "${ALLOWLIST}"; then
    echo "ℹ ${rel} : allowlisté (déduplication #6278 en cours, issue #6572)."
    continue
  fi
  echo "::error::Service/Job dans la couche Application : ${rel} → déplacer sous Infrastructure/{Services,Jobs}/ (issue #6571)." >&2
  violations=$((violations + 1))
done

if [[ "${violations}" -gt 0 ]]; then
  echo "::error::Placement couche Application : ${violations} violation(s) (issue #6571)." >&2
  exit 1
fi

echo "OK — aucun Service/Job hors allowlist dans Application/ (issue #6571)."
