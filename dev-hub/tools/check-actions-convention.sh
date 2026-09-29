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
# Dette legacy (27 violations au 2026-09-28, inventaire #8239) : les fichiers
# listés dans dev-hub/tools/actions-convention-allowlist.txt sont signalés en
# ::notice:: au lieu de ::error:: — la garde reste VERTE sur l'existant mais
# ROUGE sur toute violation NOUVELLE, et ROUGE aussi sur une entrée d'allowlist
# devenue orpheline (violation résorbée ou fichier déplacé : purger la liste
# dans la même PR, anti-régression — même règle que les allowlists openapi).
#
# Usage : bash dev-hub/tools/check-actions-convention.sh [--strict] [--allowlist <fichier>] [api_dir]
#   --strict     ignore l'allowlist (mesure la dette réelle, sortie 1 si dette)
#   --allowlist  chemin alternatif de la liste d'exclusion
#
set -euo pipefail

STRICT=0
ALLOWLIST=""
POSITIONAL=()
while [[ $# -gt 0 ]]; do
  case "$1" in
    --strict) STRICT=1; shift ;;
    --allowlist) ALLOWLIST="$2"; shift 2 ;;
    -h|--help) grep '^#' "$0" | head -30; exit 0 ;;
    *) POSITIONAL+=("$1"); shift ;;
  esac
done
set -- ${POSITIONAL[@]+"${POSITIONAL[@]}"}

API_DIR="${1:-api}"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "${ROOT}"
ALLOWLIST="${ALLOWLIST:-dev-hub/tools/actions-convention-allowlist.txt}"

errors=0
warnings=0
legacy=0

# Chargement de l'allowlist (lignes vides et commentaires ignorés).
declare -A ALLOWED=()
if [[ "${STRICT}" -eq 0 && -f "${ALLOWLIST}" ]]; then
  while IFS= read -r line; do
    [[ -z "${line}" || "${line}" =~ ^[[:space:]]*# ]] && continue
    ALLOWED["${line}"]=1
  done < "${ALLOWLIST}"
fi

mapfile -t ACTIONS < <(find "${API_DIR}/app/Modules" -path "*/Application/Actions/*.php" | sort)

if [[ ${#ACTIONS[@]} -eq 0 ]]; then
  echo "OK — aucune action dans Application/Actions/ (issue #6570)."
  exit 0
fi

# Fichiers en violation (pour la détection d'entrées orphelines).
declare -A VIOLATING=()

for f in "${ACTIONS[@]}"; do
  rel="${f#"${API_DIR}"/}"
  base="$(basename "${f}" .php)"

  file_errors=0

  if ! grep -q "function execute(" "${f}"; then
    file_errors=$((file_errors + 1))
  fi

  # Un Service/Job résiduel n'a PAS de execute() (les Actions en ont toutes une).
  # NB : PostJob est une Action légitime (verbe+objet) — le suffixe seul ne suffit pas.
  if [[ "${base}" == *Service || "${base}" == *Job ]] && ! grep -q "function execute(" "${f}"; then
    file_errors=$((file_errors + 1))
  fi

  if [[ "${file_errors}" -gt 0 ]]; then
    VIOLATING["${rel}"]=1
    if [[ -n "${ALLOWED[${rel}]:-}" ]]; then
      echo "::notice::Violation legacy tolérée (allowlist #8239, à résorber dans le lot du module) : ${rel}"
      legacy=$((legacy + 1))
    else
      if ! grep -q "function execute(" "${f}"; then
        echo "::error::Action sans execute() : ${rel} → 1 action = 1 execute() (issue #6570)." >&2
      fi
      if [[ "${base}" == *Service || "${base}" == *Job ]] && ! grep -q "function execute(" "${f}"; then
        echo "::error::Service/Job dans Application/Actions/ : ${rel} → Infrastructure/{Services,Jobs}/ (issue #6570)." >&2
      fi
      errors=$((errors + file_errors))
    fi
  fi

  if [[ "${base}" != *Action ]]; then
    echo "::warning::Action sans suffixe 'Action' : ${rel} (renommage en lot dédié, issue #6570)." >&2
    warnings=$((warnings + 1))
  fi
done

# Anti-staleness : une entrée d'allowlist dont la violation a disparu (fichier
# corrigé, renommé ou supprimé) doit être purgée — sinon la liste ment (#3596).
if [[ "${STRICT}" -eq 0 ]]; then
  for entry in "${!ALLOWED[@]}"; do
    if [[ -z "${VIOLATING[${entry}]:-}" ]]; then
      echo "::error::Entrée allowlist orpheline : ${entry} — la violation est résorbée (ou le fichier a bougé), retirer la ligne de ${ALLOWLIST} dans la même PR (anti-régression #3596)." >&2
      errors=$((errors + 1))
    fi
  done
fi

if [[ "${errors}" -gt 0 ]]; then
  echo "::error::Convention Actions : ${errors} erreur(s) bloquante(s) (issue #6570)." >&2
  exit 1
fi

echo "OK — ${#ACTIONS[@]} actions conformes execute()/placement (${warnings} sans suffixe Action, lot séparé ; ${legacy} violation(s) legacy allowlistées #8239)."
