#!/usr/bin/env bash
# ============================================================================
# check-workflow-paths.sh — garde anti paths orphelins dans les workflows CI
# (issue #3519)
#
# Pourquoi : backend-jobs-ci.yml filtrait sur des fichiers supprimés lors de
# la modularisation DDD (api/app/Services/... → api/app/Modules/...). Résultat :
# le workflow « Jobs & Queues Contracts » ne se déclenchait plus JAMAIS sur le
# code réel — trou de couverture silencieux sur les chemins argent (webhooks
# Billing, paie).
#
# Ce script échoue si une entrée `paths:` d'un workflow ne matche aucun
# fichier suivi par git. (Note : on évite `grep -q` en pipe — sous pipefail,
# son early-exit SIGPIPE git et fausse le statut ; on teste la chaîne vide.)
# ============================================================================
set -euo pipefail

cd "$(git rev-parse --show-toplevel)"

matches_any_file() {
  local pattern="$1"
  # :(glob) = sémantique glob complète de git (** intermédiaire compris)
  [[ -n "$(git ls-files -- ":(glob)${pattern}" 2>/dev/null | head -n 1)" ]]
}

stale=0
while IFS= read -r -d '' wf; do
  in_paths=0
  lineno=0
  while IFS= read -r line <&3; do
    lineno=$((lineno + 1))
    if [[ "${line}" =~ ^[[:space:]]+paths:[[:space:]]*$ ]]; then
      in_paths=1
      continue
    fi
    if [[ ${in_paths} -eq 1 ]]; then
      pattern=""
      if [[ "${line}" =~ ^[[:space:]]+-[[:space:]]+\'([^\']+)\' ]]; then
        pattern="${BASH_REMATCH[1]}"
      elif [[ "${line}" =~ ^[[:space:]]+-[[:space:]]+\"([^\"]+)\" ]]; then
        pattern="${BASH_REMATCH[1]}"
      elif [[ -n "${line//[[:space:]]/}" && ! "${line}" =~ ^[[:space:]]+- ]]; then
        in_paths=0
        continue
      else
        continue
      fi
      [[ -z "${pattern}" ]] && continue
      if ! matches_any_file "${pattern}"; then
        # Globs de type dir/** : tester aussi la racine du glob (échapper le glob)
        root="${pattern%%/\*\*}"
        if [[ "${root}" == "${pattern}" ]] || ! matches_any_file "${root}"; then
          echo "STALE: ${wf}:${lineno} → ${pattern} (aucun fichier ne matche)"
          stale=$((stale + 1))
        fi
      fi
    fi
  done 3< "${wf}"
done < <(find .github/workflows -maxdepth 1 -name '*.yml' -print0)

if [[ ${stale} -gt 0 ]]; then
  echo "ERREUR: ${stale} entrée(s) paths: orpheline(s) — un changement sur ces fichiers ne déclenchera jamais le workflow." >&2
  exit 1
fi
echo "OK: toutes les entrées paths: des workflows matchent des fichiers réels."
