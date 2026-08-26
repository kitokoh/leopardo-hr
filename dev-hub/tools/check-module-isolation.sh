#!/usr/bin/env bash
# check-module-isolation.sh — Garde d'isolation des modules (issue #5584).
#
# Règle (api/ARCHITECTURE.md) :
#   - un module n'importe jamais directement les classes d'un autre module ;
#   - Core/ ne dépend jamais de Modules/.
#
# La dette existante (16/18 modules, 57 paires source->cible) est actée dans
# module-isolation-allowlist.txt ; cette garde :
#   1. détecte TOUT import `use App\Modules\<X>\...` dans un module != X et
#      dans Core/ ;
#   2. compare chaque paire (source, cible) à l'allowlist ;
#   3. échoue (exit 1) sur toute paire ABSENTE de l'allowlist → blocage de
#      tout NOUVEL import croisé, sans casser les refactors en cours.
#
# Usage : dev-hub/tools/check-module-isolation.sh [repo_root]
set -euo pipefail

REPO_ROOT="$(cd "${1:-$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)}" && pwd)"
API_DIR="${REPO_ROOT}/api"
ALLOWLIST="${REPO_ROOT}/dev-hub/tools/module-isolation-allowlist.txt"

if [[ ! -d "${API_DIR}/app/Modules" ]]; then
  echo "::error::api/app/Modules introuvable — API_DIR invalide ?"
  exit 1
fi

if [[ ! -f "${ALLOWLIST}" ]]; then
  echo "::error::module-isolation-allowlist.txt introuvable — la garde ne peut pas fonctionner sans allowlist."
  exit 1
fi

declare -A allowed
while IFS= read -r line; do
  [[ -z "${line}" || "${line}" == \#* ]] && continue
  allowed["${line}"]=1
done < "${ALLOWLIST}"

violations=0
report=""

# 1) Modules : `use App\Modules\<cible>\` avec cible != module courant.
while IFS= read -r module; do
  module_dir="${API_DIR}/app/Modules/${module}"
  [[ -d "${module_dir}" ]] || continue
  while IFS= read -r file; do
    while IFS= read -r target; do
      [[ -z "${target}" ]] && continue
      if [[ "${target}" != "${module}" ]]; then
        pair="${module}:${target}"
        if [[ -z "${allowed[${pair}]+x}" ]]; then
          violations=$((violations + 1))
          report+="❌ ${file}: import de Modules/${target} (paire ${pair} absente de l'allowlist)\n"
        fi
      fi
    done < <(grep -oE '^[[:space:]]*use[[:space:]]+App\\Modules\\[A-Za-z0-9_]+' "${file}" | sed -E 's/.*Modules\\//' | sort -u)
  done < <(find "${module_dir}" -type f -name '*.php' | sort)
done < <(find "${API_DIR}/app/Modules" -maxdepth 1 -mindepth 1 -type d -printf '%f\n' | sort)

# 2) Core : `use App\Modules\<cible>\` (n'importe quel fichier Core).
api_prefix="${API_DIR}/app/"
while IFS= read -r file; do
  rel="${file#"$api_prefix"}"
  while IFS= read -r target; do
    [[ -z "${target}" ]] && continue
    pair="${rel}:${target}"
    if [[ -z "${allowed[${pair}]+x}" ]]; then
      violations=$((violations + 1))
      report+="❌ ${rel}: import de Modules/${target} (paire ${pair} absente de l'allowlist)\n"
    fi
  done < <(grep -oE '^[[:space:]]*use[[:space:]]+App\\Modules\\[A-Za-z0-9_]+' "${file}" | sed -E 's/.*Modules\\//' | sort -u)
done < <(find "${API_DIR}/app/Core" -type f -name '*.php' | sort)

if [[ ${violations} -gt 0 ]]; then
  echo -e "${report}" >&2
  echo "::error::Isolation des modules : ${violations} nouvel(le)(s) paire(s) d'import croisé non allowlistée(s). Ajouter une justification dans dev-hub/tools/module-isolation-allowlist.txt (ou mieux : résorber la dette, ex. #5591)."
  exit 1
fi

echo "✓ Isolation des modules : 0 nouvel import croisé (allowlist $(grep -vcE '^\s*#|^\s*$' "${ALLOWLIST}") paires actées)."
exit 0
