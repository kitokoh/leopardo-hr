#!/usr/bin/env bash
#
# check-policies-single-point.sh — garde CI « point d'enregistrement unique des
# Gate::policy » (issue #6575, PA2-ARCH-008).
#
# Tous les Gate::policy(...) du backend doivent vivre dans
# api/app/Providers/AuthServiceProvider.php (règle actée AppServiceProvider:
# « PA2-ARCH-008 : point d'enregistrement unique »). Un enregistrement dispersé
# (provider de module, service provider tiers) est une dérogation silencieuse
# difficile à auditer — ce garde l'attrape à la PR.
#
# Usage : bash dev-hub/tools/check-policies-single-point.sh [api_dir]
#
set -euo pipefail

API_DIR="${1:-api}"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "${ROOT}"

CANONICAL="api/app/Providers/AuthServiceProvider.php"
if [[ ! -f "${CANONICAL}" ]]; then
  echo "::error::AuthServiceProvider canonique introuvable : ${CANONICAL} (issue #6575)." >&2
  exit 1
fi

# Toutes les instructions Gate::policy(...) hors du fichier canonique.
# Seules les vraies instructions comptent (ligne commençant par Gate::policy),
# pas les mentions dans les commentaires/docblocks.
OFFENDERS=$(rg -l '^\s*Gate::policy\(' "${API_DIR}/app" 2>/dev/null | grep -v "^${CANONICAL}$" || true)

if [[ -n "${OFFENDERS}" ]]; then
  echo "::error::Gate::policy enregistrés hors du point unique ${CANONICAL} (PA2-ARCH-008, issue #6575) :" >&2
  printf '%s\n' "${OFFENDERS}" | sed 's/^/  /' >&2
  exit 1
fi

# Doublons d'inscription DANS le fichier canonique (issue #8177) :
# `Gate::policy()` écrase silencieusement l'enregistrement précédent — la
# DERNIÈRE ligne gagne, donc un modèle inscrit 2× rend la policy effective
# dépendante de l'ordre des lignes (incident production : 7 modèles Fuel
# inscrits 2 à 3 fois, opérateurs 403 sur le signalement d'incident).
DUPLICATES=$(
  rg -o '^\s*Gate::policy\(([A-Za-z0-9_]+)::class' "${CANONICAL}" -r '$1' \
    | sort | uniq -d || true
)

if [[ -n "${DUPLICATES}" ]]; then
  echo "::error::Gate::policy en double dans ${CANONICAL} — écrasement silencieux (issue #8177), un seul enregistrement par modèle :" >&2
  printf '%s\n' "${DUPLICATES}" | sed 's/^/  /' >&2
  exit 1
fi

echo "OK — tous les Gate::policy vivent dans ${CANONICAL} (issue #6575) et aucun modèle n'est inscrit en double (issue #8177)."
