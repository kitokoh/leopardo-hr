#!/usr/bin/env bash
# check-no-claim-marker.sh — Garde CI « .claim-marker jamais commité » (issue #5447)
#
# Pourquoi : la convention PLAN_100PCT.md §6.4 (« .claim-marker et fichiers
# générés : jamais commités ») est violée régulièrement par les agents parallèles
# (#5261, #5353, #5354, #5355 — 52 branches + main constatés le 2026-08-25).
# Chaque marqueur commité pollue l'historique, crée des conflits de merge (le
# fichier est modifié par tous les agents) et ajoute du bruit aux revues.
#
# Le marqueur de claim est un LOCK LOCAL : le créér côté agent (fichier non
# tracké, ex. .gitignore) ou utiliser un commit vide « claim marker #N »
# (protocole #2400) — ne JAMAIS commiter de fichier .claim-marker.
#
# Usage : dev-hub/tools/check-no-claim-marker.sh [repo_root]
#   repo_root : racine du dépôt (défaut : .)
#
# Échoue (exit 1) si un fichier nommé `.claim-marker` existe dans l'arbre de
# travail (hors .git). Branché dans architecture-check.yml (hygiene-guards).
set -uo pipefail

ROOT="${1:-.}"

found=()
while IFS= read -r f; do
  found+=("${f}")
done < <(find "${ROOT}" -name '.claim-marker' -not -path '*/.git/*' 2>/dev/null | sort)

if [[ "${#found[@]}" -gt 0 ]]; then
  echo "::error::Garde .claim-marker (issue #5447) : ${#found[@]} fichier(s) .claim-marker dans l'arbre de travail — convention PLAN_100PCT.md §6.4 violée :"
  for f in "${found[@]}"; do
    echo "::error::  ${f}"
  done
  echo "::error::→ Le marqueur de claim est un LOCK LOCAL : ne pas le commiter. Retirer le(s) fichier(s) de la branche avant push (git rm + commit), puis re-pousser."
  exit 1
fi

echo "✓ Aucun .claim-marker dans l'arbre de travail (convention §6.4 respectée)"
exit 0
