#!/usr/bin/env bash
# check-public-surfaces-deploy-parity.sh — garde de parité vitrines ↔ déploiement
#
# Issue #9591. Le déploiement conditionnel par vitrine repose sur un miroir :
# `detect-changed-surfaces.sh` porte sa propre table `SURFACES` (vertical +
# basePath), et le workflow `public-surfaces-deploy.yml` porte sa matrice.
#
# Un miroir qui dérive est pire qu'aucun miroir : ajouter une septième
# verticale dans `SURFACE_VERTICALS` sans ligne dans le détecteur produirait une
# vitrine qui ne se déploie JAMAIS — silencieusement, et seulement en prod.
#
# Cette garde compare les trois sources de vérité :
#   1. `SURFACE_VERTICALS` (kernel/domain/surface-identity.ts) — la vérité code ;
#   2. la table `SURFACES` de `detect-changed-surfaces.sh` — la vérité CI ;
#   3. les identités `modules/public-surfaces/<v>/identity/*-identity.ts` —
#      le `basePath` réellement servi.
#
# Exit 1 si une verticale manque d'un côté, si un basePath diverge, ou si une
# identité déclare un domaine absent du registre `docs/ops/DOMAINS.md`.
#
# Usage : dev-hub/tools/check-public-surfaces-deploy-parity.sh [repo_root]

set -euo pipefail

ROOT="${1:-$(git rev-parse --show-toplevel 2>/dev/null || true)}"
if [[ -z "${ROOT}" ]]; then
  ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
fi
cd "${ROOT}"

IDENTITY_FILE="front/web/src/modules/public-surfaces/kernel/domain/surface-identity.ts"
DETECTOR="dev-hub/tools/detect-changed-surfaces.sh"
MODULE_DIR="front/web/src/modules/public-surfaces"

errors=0
fail() { echo "::error::$*"; errors=$((errors + 1)); }

if [[ ! -f "${IDENTITY_FILE}" ]]; then
  fail "fichier d'identité introuvable : ${IDENTITY_FILE}"
  exit 1
fi

if [[ ! -f "${DETECTOR}" ]]; then
  fail "détecteur introuvable : ${DETECTOR}"
  exit 1
fi

# --- 1. Verticales déclarées dans le code ------------------------------------
# Bloc `SURFACE_VERTICALS = [ 'a', 'b', ... ] as const`.
CODE_VERTICALS="$(
  sed -n '/SURFACE_VERTICALS = \[/,/\] as const/p' "${IDENTITY_FILE}" \
    | grep -oE "'[a-z_]+'" \
    | tr -d "'" \
    | sort -u
)"

if [[ -z "${CODE_VERTICALS}" ]]; then
  fail "aucune verticale extraite de SURFACE_VERTICALS — le format du fichier a changé"
  exit 1
fi

# --- 2. Verticales du détecteur ----------------------------------------------
DETECTOR_VERTICALS="$(
  sed -n '/^SURFACES=(/,/^)/p' "${DETECTOR}" \
    | grep -oE "\\\$'[a-z_]+\\\\t" \
    | sed "s/\\\$'//; s/\\\\t//" \
    | sort -u
)"

if [[ -z "${DETECTOR_VERTICALS}" ]]; then
  fail "aucune verticale extraite de la table SURFACES du détecteur"
  exit 1
fi

# --- 3. Comparaison code ↔ détecteur -----------------------------------------
while IFS= read -r vertical; do
  [[ -z "${vertical}" ]] && continue
  if ! grep -qx "${vertical}" <<< "${DETECTOR_VERTICALS}"; then
    fail "verticale '${vertical}' déclarée dans SURFACE_VERTICALS mais ABSENTE du détecteur ${DETECTOR} — sa vitrine ne serait jamais déployée (#9591)"
  fi
done <<< "${CODE_VERTICALS}"

while IFS= read -r vertical; do
  [[ -z "${vertical}" ]] && continue
  if ! grep -qx "${vertical}" <<< "${CODE_VERTICALS}"; then
    fail "verticale '${vertical}' présente dans le détecteur mais ABSENTE de SURFACE_VERTICALS — entrée morte"
  fi
done <<< "${DETECTOR_VERTICALS}"

# --- 4. basePath : identité réelle ↔ détecteur -------------------------------
# Le nom du dossier de bounded context n'est pas toujours la verticale
# (`restaurant` vit dans `restaurants/`). Cette table est la seule exception
# tolérée, et elle est explicite : une verticale sans entrée retombe sur son
# propre nom.
module_dir_for() {
  case "$1" in
    restaurant) echo "restaurants" ;;
    *) echo "$1" ;;
  esac
}

# basePath déclaré par le détecteur pour une verticale.
#
# Les lignes du détecteur ont la forme `  $'restaurant\t/restaurants'` : le
# séparateur est la séquence LITTÉRALE `\t` (deux caractères), pas une tabulation
# — d'où le découpage explicite plutôt qu'un `IFS` ou un motif bash, où `\t`
# serait interprété comme la lettre `t`.
detector_base_path() {
  sed -n '/^SURFACES=(/,/^)/p' "${DETECTOR}" \
    | sed "s/^[[:space:]]*\\\$'//; s/'[[:space:]]*$//" \
    | awk -v v="$1" 'index($0, v "\\t") == 1 { print substr($0, length(v) + 3); exit }'
}

while IFS= read -r vertical; do
  [[ -z "${vertical}" ]] && continue

  dir="$(module_dir_for "${vertical}")"
  identity_file="$(find "${MODULE_DIR}/${dir}/identity" -name '*-identity.ts' 2>/dev/null | head -1)"

  if [[ -z "${identity_file}" ]]; then
    fail "verticale '${vertical}' sans fichier d'identité dans ${MODULE_DIR}/${dir}/identity/"
    continue
  fi

  declared_path="$(grep -oE "basePath: '[^']+'" "${identity_file}" | head -1 | sed "s/basePath: '//; s/'//")"

  if [[ -z "${declared_path}" ]]; then
    fail "verticale '${vertical}' : basePath introuvable dans ${identity_file}"
    continue
  fi

  detector_path="$(detector_base_path "${vertical}")"

  if [[ "${declared_path}" != "${detector_path}" ]]; then
    fail "verticale '${vertical}' : basePath '${declared_path}' (identité) ≠ '${detector_path}' (détecteur) — le déploiement ne suivrait pas la bonne route"
  fi

  # La route doit exister : un basePath sans page ne déploie rien d'utile.
  if [[ ! -d "front/web/src/app${declared_path}" ]]; then
    fail "verticale '${vertical}' : aucune route 'front/web/src/app${declared_path}' — la vitrine n'est pas servie"
  fi
done <<< "${CODE_VERTICALS}"

# --- 5. Domaine canonique déclaré dans le registre ops -----------------------
DOMAINS_DOC="docs/ops/DOMAINS.md"
if [[ -f "${DOMAINS_DOC}" ]]; then
  while IFS= read -r vertical; do
    [[ -z "${vertical}" ]] && continue

    identity_file="$(find "${MODULE_DIR}/$(module_dir_for "${vertical}")/identity" -name '*-identity.ts' 2>/dev/null | head -1)"
    [[ -z "${identity_file}" ]] && continue

    domain="$(grep -oE "canonicalDomain: '[^']+'" "${identity_file}" | head -1 | sed "s/canonicalDomain: '//; s/'//")"
    [[ -z "${domain}" ]] && continue

    if ! grep -qF "${domain}" "${DOMAINS_DOC}"; then
      fail "verticale '${vertical}' : domaine '${domain}' absent de ${DOMAINS_DOC} — la garde anti-drift des domaines le refuserait"
    fi
  done <<< "${CODE_VERTICALS}"
fi

# --- 6. Verticales en attente de provisionnement Vercel (#9384) ----------------
# `PENDING_VERCEL_SURFACES` existe pour qu'une vitrine non provisionnée ne rende
# pas `main` rouge en permanence. Le risque de cette liste est qu'elle pourrisse :
# une verticale dont les secrets ont FINI d'être posés resterait « en attente »
# et ne serait plus jamais déployée — silencieusement. Cette section l'interdit.
#
# Elle vérifie deux choses, sans réseau (les secrets ne sont pas lisibles ici) :
#   1. chaque entrée est une verticale connue du détecteur (pas de faute de frappe
#      qui ne matcherait jamais et laisserait la vraie verticale échouer) ;
#   2. chaque entrée est documentée dans le runbook avec son issue de
#      provisionnement — une entrée non documentée est une dette invisible.
PENDING_BLOCK="$(sed -n '/^PENDING_VERCEL_SURFACES=(/,/^)/p' "${DETECTOR}")"
if [[ -n "${PENDING_BLOCK}" ]]; then
  PENDING_VERTICALS="$(
    grep -oE "\\\$'[a-z_]+'" <<< "${PENDING_BLOCK}" | sed "s/\\\$'//; s/'//" | sort -u
  )"
  RUNBOOK="docs/ops/PUBLIC_SURFACES_DEPLOY.md"

  while IFS= read -r vertical; do
    [[ -z "${vertical}" ]] && continue

    if ! grep -qx "${vertical}" <<< "${DETECTOR_VERTICALS}"; then
      fail "PENDING_VERCEL_SURFACES contient '${vertical}', absent de la table SURFACES — faute de frappe : la vraie verticale échouerait toujours sur push main (#9384)"
    fi

    if [[ -f "${RUNBOOK}" ]] && ! grep -qF "${vertical}" "${RUNBOOK}"; then
      fail "verticale '${vertical}' déclarée EN ATTENTE de provisionnement mais absente de ${RUNBOOK} — le retard doit être documenté, pas seulement codé (#9384)"
    fi
  done <<< "${PENDING_VERTICALS}"
fi

if [[ "${errors}" -gt 0 ]]; then
  echo ""
  echo "Parité vitrines ↔ déploiement : ${errors} écart(s) (#9591)."
  exit 1
fi

echo "Parité vitrines ↔ déploiement : OK ($(wc -l <<< "${CODE_VERTICALS}" | tr -d ' ') verticales, basePath et domaines alignés)."
