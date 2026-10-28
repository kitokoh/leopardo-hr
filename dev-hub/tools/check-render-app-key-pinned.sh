#!/usr/bin/env bash
# check-render-app-key-pinned.sh — Garde CI « APP_KEY epingle » (issue #7967)
#
# Constat (audit 2026-09-20, #7967) : les blueprints Render prod declaraient
# `APP_KEY: generateValue: true`. Une re-sync du blueprint REGENERE la cle et
# invalide d'un coup toutes les sessions ET toutes les valeurs chiffrees en
# base (tokens OAuth, secrets de webhook, donnees sensibles).
#
# Correctif applique : `APP_KEY` porte desormais `sync: false` (valeur epinglee
# dans le dashboard Render) dans TOUS les blueprints. Cette garde empeche la
# regression :
#   - aucun `generateValue: true` actif (non commente) dans les blueprints ;
#   - tout `- key: APP_KEY` actif est immediatement suivi d'un `sync: false`.
#
# Usage : dev-hub/tools/check-render-app-key-pinned.sh [repo_root]
# Exit codes : 0 = OK, 1 = violation.

set -uo pipefail

ROOT="${1:-.}"
ERRORS=0

BLUEPRINTS=()
while IFS= read -r f; do
  BLUEPRINTS+=("${f}")
done < <(find "${ROOT}" -maxdepth 1 \( -name 'render*.yaml' -o -name 'render*.yml' \) | sort)

if [[ ${#BLUEPRINTS[@]} -eq 0 ]]; then
  echo "check-render-app-key-pinned : aucun blueprint render*.yaml a la racine — rien a verifier."
  exit 0
fi

for bp in "${BLUEPRINTS[@]}"; do
  rel="${bp#"${ROOT}"/}"

  # 1. generateValue ne doit subsister qu'en commentaire.
  while IFS=: read -r lineno content; do
    trimmed="${content#"${content%%[![:space:]]*}"}"
    if [[ "${trimmed}" != \#* ]] && [[ "${content}" == *"generateValue: true"* ]]; then
      echo "::error::${rel}:${lineno} : generateValue: true actif. Une re-sync du blueprint regenererait APP_KEY et invaliderait sessions et valeurs chiffrees (#7967). Utiliser 'sync: false' + valeur epinglee dans le dashboard Render." >&2
      ERRORS=$((ERRORS + 1))
    fi
  done < <(grep -n "generateValue" "${bp}" || true)

  # 2. Chaque APP_KEY actif doit etre suivi de 'sync: false'.
  while IFS= read -r line; do
    lineno="${line%%:*}"
    content="${line#*:}"
    trimmed="${content#"${content%%[![:space:]]*}"}"
    [[ "${trimmed}" == \#* ]] && continue

    window=$(awk -v s="${lineno}" 'NR>s && NR<=s+6' "${bp}")
    if ! grep -qE '^[[:space:]]+sync:[[:space:]]*false[[:space:]]*$' <<<"${window}"; then
      echo "::error::${rel}:${lineno} : APP_KEY sans 'sync: false' dans les 6 lignes suivantes. La cle doit etre epinglee (dashboard Render) pour survivre aux re-syncs de blueprint (#7967)." >&2
      ERRORS=$((ERRORS + 1))
    fi
  done < <(grep -nE '^[[:space:]]*(-[[:space:]]*)?key:[[:space:]]*APP_KEY[[:space:]]*$' "${bp}" || true)
done

if [[ "${ERRORS}" -eq 0 ]]; then
  echo "✓ APP_KEY epinglee (sync: false) dans les ${#BLUEPRINTS[@]} blueprints Render, aucun generateValue actif (issue #7967)."
  exit 0
fi

exit 1
