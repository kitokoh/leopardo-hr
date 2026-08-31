#!/usr/bin/env bash
# check-layer-purity.sh — garde CI issue #6568 (audit DDD H1-H3, 2026-08-31)
#
# Interdit, dans le périmètre DDD (api/app/Modules/* et api/app/Core/*) :
#   1. les imports de Application/ ou Infrastructure/ depuis Domain/
#      (règle « Interfaces → Application → Domain » — le Domain ne dépend
#      jamais vers le bas) ;
#   2. `use Illuminate\Support\Facades\*` depuis Domain/ et Application/
#      (le Domain et l'Application ne touchent pas les facades Laravel ;
#      seul Eloquent est toléré dans les modèles).
#
# Le code legacy hors modules (app/AI, app/Jobs, app/Listeners, app/Http,
# app/Console...) est HORS périmètre — suivi séparément par l'issue #6578
# (legacy hors modules).
#
# Les violations EXISTANTES documentées vivent dans layer-purity-allowlist.txt.
# Ce fichier est immuable depuis CI : tout nouvel import interdit fait échouer
# la PR (même mécanique que module-isolation-allowlist.txt, issue #5584).
#
# Usage : bash dev-hub/tools/check-layer-purity.sh [api_dir]
# Sortie : 0 = OK, 1 = violations nouvelles/allowlist modifiée.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ALLOWLIST="${SCRIPT_DIR}/layer-purity-allowlist.txt"
API_DIR="${1:-api}"
APP_DIR="${API_DIR}/app"

if [[ ! -d "${APP_DIR}" ]]; then
  echo "❌  Répertoire '${APP_DIR}' introuvable." >&2
  exit 1
fi

if [[ ! -f "${ALLOWLIST}" ]]; then
  echo "❌  Allowlist introuvable : ${ALLOWLIST}" >&2
  exit 1
fi

# ── Collecte des violations ───────────────────────────────────────────────────
# Ligne de violation : <chemin relatif>:<n° ligne>:<use ...>

TMP_FILE="$(mktemp)"
trap 'rm -f "${TMP_FILE}"' EXIT

while IFS= read -r -d '' file; do
  rel="${file#./}"
  case "${rel}" in
    "${APP_DIR}"/Modules/*|"${APP_DIR}"/Core/*)
      ;;
    *)
      continue
      ;;
  esac

  # 1) imports Application/Infrastructure depuis Domain
  if [[ "${rel}" == *"/Domain/"* ]]; then
    grep -nE '^use App\\(Modules|Core)[^;]*\\(Application|Infrastructure)\\' "${file}" 2>/dev/null \
      | sed "s|^|${rel}:|" || true
  fi

  # 2) facades Laravel depuis Domain/ et Application/ (Interfaces/,
  #    Infrastructure/ et Providers/ sont autorisés)
  if [[ "${rel}" == *"/Domain/"* || "${rel}" == *"/Application/"* ]]; then
    grep -nE '^use Illuminate\\Support\\Facades\\' "${file}" 2>/dev/null \
      | sed "s|^|${rel}:|" || true
  fi
done < <(find . -path "./${APP_DIR}/Modules/*" -name '*.php' -print0; find . -path "./${APP_DIR}/Core/*" -name '*.php' -print0) \
  | sort -u > "${TMP_FILE}"

# ── Comparaison à l'allowlist ─────────────────────────────────────────────────
# L'allowlist est immuable : la supprimer ou l'alléger fait aussi échouer
# (une violation corrigée doit laisser la ligne en place jusqu'au nettoyage
# manuel du fichier, comme module-isolation-allowlist.txt).

NEW_VIOLATIONS=$(comm -23 "${TMP_FILE}" "${ALLOWLIST}")
ALLOWLIST_DELETED=$(comm -13 "${TMP_FILE}" "${ALLOWLIST}")

if [[ -n "${NEW_VIOLATIONS}" ]]; then
  echo "❌  Violations de pureté de couches NON allowlistées :" >&2
  echo "${NEW_VIOLATIONS}" >&2
  echo "" >&2
  echo "Rappel (issue #6568) : le Domain ne doit pas importer Application/Infrastructure," >&2
  echo "et les facades Laravel sont réservées à Interfaces/Infrastructure (et assimilés)." >&2
  exit 1
fi

if [[ -n "${ALLOWLIST_DELETED}" ]]; then
  echo "❌  L'allowlist layer-purity-allowlist.txt contient des entrées qui n'existent plus :" >&2
  echo "${ALLOWLIST_DELETED}" >&2
  echo "Ce fichier est immuable depuis CI (pattern #5584) — ne pas le modifier ici." >&2
  exit 1
fi

echo "✅  check-layer-purity : 0 violation nouvelle ($(wc -l < "${ALLOWLIST}" | tr -d ' ') entrées allowlistées)."
