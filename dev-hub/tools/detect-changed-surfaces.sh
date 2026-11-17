#!/usr/bin/env bash
# detect-changed-surfaces.sh — quelles vitrines publiques doivent être déployées ?
#
# Issue #9591. Le dépôt avait déjà ce principe pour la Vitrine, l'API et les
# apps mobiles : un front n'est redéployé que si du code qui le concerne a
# changé. Les vitrines verticales grand public n'avaient rien — un correctif
# dans `modules/public-surfaces/pharmacy/**` aurait reconstruit les six.
#
# ## Périmètre d'une vitrine
#
# Une vitrine verticale est concernée par un diff dès qu'il touche :
#   - son bounded context  : front/web/src/modules/public-surfaces/<v>/**
#   - sa route publique    : front/web/src/app/<basePath>/**
#   - ou le NOYAU partagé  : kernel/**, copy/**, registry.ts, index.ts
#
# Une modification du noyau concerne TOUTES les vitrines — c'est le prix de la
# factorisation : le noyau porte le contraste, l'identité et le rendu communs,
# donc le corriger change le rendu de chaque surface. Le nier produirait des
# vitrines désynchronisées.
#
# ## Sortie
#
# Une ligne JSON par exécution :
#   {"surfaces":["pharmacy","restaurants"],"shared":false,"all":false}
#
# `surfaces` liste les verticales touchées (vide si aucune). `all: true` quand
# le noyau a changé (toutes les vitrines doivent partir). Les consommateurs
# (workflow `public-surfaces-deploy.yml`) utilisent `surfaces` comme matrice.
#
# ## Usage
#
#   dev-hub/tools/detect-changed-surfaces.sh --base origin/main --head HEAD
#   git diff --name-only | dev-hub/tools/detect-changed-surfaces.sh --stdin
#
# Exit 1 sur argument inconnu ou sur `git diff` impossible : un échec silencieux
# ici déploierait zéro vitrine alors qu'un diff existe (pire que déployer trop).

set -euo pipefail

BASE=""
HEAD="HEAD"
FROM_STDIN=0

while [[ $# -gt 0 ]]; do
  case "$1" in
    --base) BASE="${2:-}"; shift 2 ;;
    --head) HEAD="${2:-}"; shift 2 ;;
    --stdin) FROM_STDIN=1; shift ;;
    -h|--help)
      sed -n '2,30p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'
      exit 0
      ;;
    *) echo "::error::argument inconnu : $1" >&2; exit 1 ;;
  esac
done

# --- Registre des vitrines ---------------------------------------------------
# vertical<TAB>basePath. Miroir de SURFACE_VERTICALS et des identités
# (modules/public-surfaces/<v>/identity/*-identity.ts). La garde
# `check-public-surfaces-deploy-parity.sh` vérifie que ce miroir est complet :
# ajouter une verticale sans ligne ici fait échouer la CI.
SURFACES=(
  $'restaurant\t/restaurants'
  $'retail\t/boutiques'
  $'hospitality\t/stay'
  $'trade\t/entreprises'
  $'travel\t/travel'
  $'pharmacy\t/pharmacies'
)

if [[ "${FROM_STDIN}" -eq 1 ]]; then
  CHANGED="$(cat)"
else
  if [[ -z "${BASE}" ]]; then
    echo "::error::--base requis (ou --stdin)" >&2
    exit 1
  fi
  CHANGED="$(git diff --name-only "${BASE}...${HEAD}" || {
    echo "::error::git diff ${BASE}...${HEAD} impossible" >&2
    exit 1
  })"
fi

# --- Détection ---------------------------------------------------------------
declare -A HIT=()
SHARED=0

while IFS= read -r path; do
  [[ -z "${path}" ]] && continue

  # Noyau partagé : toute la surface est concernée.
  case "${path}" in
    front/web/src/modules/public-surfaces/kernel/*|\
    front/web/src/modules/public-surfaces/copy/*|\
    front/web/src/modules/public-surfaces/registry.ts|\
    front/web/src/modules/public-surfaces/index.ts|\
    .github/workflows/public-surfaces-deploy.yml|\
    dev-hub/tools/detect-changed-surfaces.sh)
      SHARED=1
      continue
      ;;
  esac

  for entry in "${SURFACES[@]}"; do
    vertical="${entry%%$'\t'*}"
    base_path="${entry##*$'\t'}"

    # Bounded context de la verticale.
    if [[ "${path}" == "front/web/src/modules/public-surfaces/${vertical}/"* ]]; then
      HIT["${vertical}"]=1
      continue 2
    fi

    # Route publique de la verticale (`/stay` couvre `/stay/**`).
    if [[ "${path}" == "front/web/src/app${base_path}" || "${path}" == "front/web/src/app${base_path}/"* ]]; then
      HIT["${vertical}"]=1
      continue 2
    fi
  done
done <<< "${CHANGED}"

if [[ "${SHARED}" -eq 1 ]]; then
  for entry in "${SURFACES[@]}"; do
    HIT["${entry%%$'\t'*}"]=1
  done
fi

# --- Sortie JSON (triée : la matrice CI doit être stable d'un run à l'autre) --
LIST=""
for entry in "${SURFACES[@]}"; do
  vertical="${entry%%$'\t'*}"
  if [[ -n "${HIT[${vertical}]:-}" ]]; then
    LIST="${LIST:+${LIST},}\"${vertical}\""
  fi
done

ALL="false"
if [[ "${SHARED}" -eq 1 ]]; then
  ALL="true"
fi

printf '{"surfaces":[%s],"shared":%s,"all":%s}\n' "${LIST}" \
  "$([[ "${SHARED}" -eq 1 ]] && echo true || echo false)" "${ALL}"
