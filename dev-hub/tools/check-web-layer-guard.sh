#!/usr/bin/env bash
#
# check-web-layer-guard.sh — garde de couche du front web (#9652, #9653).
#
# ## Pourquoi ce script existe
#
# ADR-0034 fait de `front/web/src/modules/public-surfaces` la norme du front
# grand public : découpe en cinq couches, `domain/` sans dépendance vers le
# haut. Mais la règle n'était opposable NULLE PART :
#
#   - `dev-hub/tools/check-layer-purity.sh` ne couvre que le PHP
#     (`api/app/Modules`, `api/app/Core`) ;
#   - `modules/public-surface/__tests__/layer-guard.test.ts` couvre le module
#     SINGULIER `public-surface` (13 fichiers), pas le pluriel qui porte les
#     8 verticales (173 fichiers) ;
#   - `modules/vitrine` (188 fichiers) n'a aucune garde.
#
# Une règle qui n'a pas de garde est une intention de revue. Elle ne survit pas
# à trois mois de pression produit.
#
# ## Ce qu'il refuse
#
# Dans tout `<module>/**/domain/` déclaré `layered` :
#   - un import de framework UI / runtime (`react`, `react-dom`, `next`,
#     `next/*`) ;
#   - un import d'une couche supérieure (`application/`, `infrastructure/`,
#     `components/`, `copy/`, `data/`, `hooks/`, `lib/`, `types/`) ;
#   - un import qui remonte hors du module (`../../../`) — le domaine ne
#     connaît que son module ;
#   - un appel réseau ou un accès navigateur (`fetch(`, `localStorage`,
#     `sessionStorage`, `document.cookie`) hors commentaire.
#
# ## Ce qu'il refuse AUSSI, et qui est le vrai apport
#
# Le registre `web-layer-registry.txt` déclare l'état de chaque module. Sans
# lui, la garde serait fail-open sur les modules futurs : un module créé sans
# `domain/` passerait au vert sans que personne n'ait décidé qu'il serait
# plat. Le registre rend la dette visible et l'empêche de grossir en silence.
#
#   - module sur disque absent du registre    → échec
#   - module du registre absent du disque     → échec
#   - `layered` sans aucun `domain/`          → échec (renommage silencieux)
#   - `flat` avec un `domain/`                → échec (le registre ment)
#
# Même doctrine que `mobile-layer-registry.txt` (#9645-#9647) et que
# `mobile-feature-duplication-baseline.txt` (#7652).
#
# ## Auto-test
#
# Le script soumet une source fautive à son propre analyseur et échoue si elle
# n'est pas signalée. Sans cela, une regex cassée passerait au vert pour
# toujours — c'est exactement le défaut que corrige l'assertion « au moins un
# fichier de domaine » côté mobile.
#
# Usage :
#   bash dev-hub/tools/check-web-layer-guard.sh
#   bash dev-hub/tools/check-web-layer-guard.sh --verbose
#
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "${ROOT}"

MODULES_DIR="front/web/src/modules"
REGISTRY="dev-hub/tools/web-layer-registry.txt"
VERBOSE=0
[[ "${1:-}" == "--verbose" ]] && VERBOSE=1

failures=0
fail() { echo "::error::$*" >&2; failures=$((failures + 1)); }
note() { [[ "${VERBOSE}" -eq 1 ]] && echo "  $*" || true; }

if [[ ! -d "${MODULES_DIR}" ]]; then
  echo "OK — ${MODULES_DIR} absent (garde sans objet)."
  exit 0
fi

if [[ ! -f "${REGISTRY}" ]]; then
  echo "::error::registre introuvable : ${REGISTRY}" >&2
  exit 1
fi

# ---------------------------------------------------------------------------
# 1. Analyseur — partagé entre la vérification réelle et l'auto-test.
# ---------------------------------------------------------------------------

# Couches qui ne doivent jamais être importées depuis `domain/`.
FORBIDDEN_LAYERS=(
  '/application/'
  '/infrastructure/'
  '/components/'
  '/copy/'
  '/data/'
  '/hooks/'
  '/types/'
)

# Paquets de framework interdits dans `domain/`.
FORBIDDEN_PACKAGES=(
  'react'
  'react-dom'
  'next'
)

# Accès réseau / navigateur interdits dans `domain/`.
FORBIDDEN_RUNTIME=(
  'fetch('
  'localStorage'
  'sessionStorage'
  'document.cookie'
  'window.'
)

# Imprime une violation par ligne pour un fichier donné.
# Sortie : "<fichier>|<raison>"
analyse_file() {
  local file="$1"
  local specifier reason

  while IFS= read -r specifier; do
    [[ -z "${specifier}" ]] && continue

    # Imports de paquet : seuls les paquets de framework sont interdits, un
    # `import type` reste possible depuis un paquet pur. On refuse la liste.
    if [[ "${specifier}" != .* && "${specifier}" != /* ]]; then
      for pkg in "${FORBIDDEN_PACKAGES[@]}"; do
        if [[ "${specifier}" == "${pkg}" || "${specifier}" == "${pkg}"/* ]]; then
          echo "${file}|package ${pkg} interdit dans domain/ : ${specifier}"
        fi
      done
      # Un alias de chemin (`@/...`) contourne la vérification relative : il
      # peut désigner n'importe quelle couche, on le refuse dans le domaine.
      if [[ "${specifier}" == @/* || "${specifier}" == ~/* ]]; then
        echo "${file}|alias de chemin interdit dans domain/ : ${specifier}"
      fi
      continue
    fi

    # `../../../` : le domaine remonte hors de son module. Les `domain/` de ce
    # dépôt vivent à `<module>/<vertical>/domain/` ou `<module>/domain/`, donc
    # trois niveaux de remontée sortent du module dans tous les cas.
    if [[ "${specifier}" == ../../../* || "${specifier}" == ../../../../* ]]; then
      echo "${file}|import hors du module depuis domain/ : ${specifier}"
      continue
    fi

    local normalized="${specifier//\\//}"
    for layer in "${FORBIDDEN_LAYERS[@]}"; do
      if [[ "${normalized}" == *"${layer}"* ]]; then
        echo "${file}|couche ${layer} importee depuis domain/ : ${specifier}"
      fi
    done
  done < <(perl -0777 -ne '
    while (/(?:^|\n)\s*(?:import|export)\s[^;\n]*?from\s+["\x27]([^"\x27]+)["\x27]/g) {
      print "$1\n";
    }
    while (/(?:^|\n)\s*import\s+["\x27]([^"\x27]+)["\x27]/g) {
      print "$1\n";
    }
  ' "${file}")

  # Accès réseau / navigateur, hors lignes de commentaire.
  #
  # Note : `grep -F` et non `perl`, parce que les motifs contiennent des
  # parenthèses et des points (`fetch(`, `window.`) que `qw()` de Perl traite
  # comme des délimiteurs. Le premier jet utilisait `qw(...)` et l'auto-test
  # l'a rejeté — c'est exactement ce pour quoi l'auto-test existe.
  local pattern
  for pattern in "${FORBIDDEN_RUNTIME[@]}"; do
    while IFS=: read -r lineno _rest; do
      [[ -z "${lineno}" ]] && continue
      echo "${file}||acces interdit dans domain/ : ${pattern} (ligne ${lineno})"
    done < <(grep -nF -- "${pattern}" "${file}" | grep -vE '^[0-9]+:[[:space:]]*(\*|//|/\*)' || true)
  done
}

# ---------------------------------------------------------------------------
# 2. Auto-test — la garde doit détecter une violation qu'on lui soumet.
# ---------------------------------------------------------------------------

autotest() {
  local tmp
  tmp="$(mktemp)"
  cat > "${tmp}" <<'TS'
import { useState } from 'react';
import Link from 'next/link';
import { SEAT_STATUS_LABELS } from '../../copy/retail-copy';
import { helper } from '../../../other-module/lib/helper';
import { thing } from '@/modules/vitrine/data/thing';
import { local } from './sibling';

const value = fetch('/api/x');
const stored = localStorage.getItem('k');
const ok = 1;
TS

  local out
  out="$(analyse_file "${tmp}")"
  rm -f "${tmp}"

  local pkg layer outside alias runtime
  pkg="$(printf '%s\n' "${out}" | grep -c 'package ' || true)"
  layer="$(printf '%s\n' "${out}" | grep -c 'couche ' || true)"
  outside="$(printf '%s\n' "${out}" | grep -c 'hors du module' || true)"
  alias="$(printf '%s\n' "${out}" | grep -c 'alias de chemin' || true)"
  runtime="$(printf '%s\n' "${out}" | grep -c 'acces interdit' || true)"

  # 2 paquets framework, 1 couche copy/, 1 import hors module, 1 alias,
  # 2 accès runtime (fetch + localStorage). `./sibling` ne doit RIEN produire.
  [[ "${pkg}" -eq 2 ]] || fail "auto-test : ${pkg} paquet(s) framework detecte(s), 2 attendus — l'analyseur est casse"
  [[ "${layer}" -eq 1 ]] || fail "auto-test : ${layer} couche(s) detectee(s), 1 attendue — l'analyseur est casse"
  [[ "${outside}" -eq 1 ]] || fail "auto-test : ${outside} import(s) hors module detecte(s), 1 attendu — l'analyseur est casse"
  [[ "${alias}" -eq 1 ]] || fail "auto-test : ${alias} alias detecte(s), 1 attendu — l'analyseur est casse"
  [[ "${runtime}" -eq 2 ]] || fail "auto-test : ${runtime} acces runtime detecte(s), 2 attendus — l'analyseur est casse"
  if printf '%s\n' "${out}" | grep -q 'sibling'; then
    fail "auto-test : './sibling' signale a tort — un import local au domaine doit rester autorise"
  fi

  note "auto-test : ${pkg} pkg, ${layer} couche, ${outside} hors-module, ${alias} alias, ${runtime} runtime — attendu 2/1/1/1/2"
}

# ---------------------------------------------------------------------------
# 3. Registre — chaque module déclaré, et l'état déclaré doit être vrai.
# ---------------------------------------------------------------------------

declare -A DECLARED_STATE=()

while IFS= read -r line; do
  [[ -z "${line}" ]] && continue
  [[ "${line}" == \#* ]] && continue

  # shellcheck disable=SC2086
  set -- ${line}
  local_module="$1"
  local_state="$2"

  if [[ -z "${local_module:-}" || -z "${local_state:-}" ]]; then
    fail "registre : ligne malformee (attendu '<module> <etat> <note>') : ${line}"
    continue
  fi

  if [[ "${local_state}" != "layered" && "${local_state}" != "flat" ]]; then
    fail "registre : etat inconnu '${local_state}' pour ${local_module} (attendu layered|flat)"
    continue
  fi

  if [[ -n "${DECLARED_STATE[${local_module}]:-}" ]]; then
    fail "registre : ${local_module} declare deux fois"
    continue
  fi

  DECLARED_STATE["${local_module}"]="${local_state}"
done < "${REGISTRY}"

# Modules réellement présents (dossiers contenant au moins un .ts/.tsx).
mapfile -t ON_DISK < <(
  find "${MODULES_DIR}" -mindepth 1 -maxdepth 1 -type d -print \
    | while read -r d; do
        if find "${d}" -type f \( -name '*.ts' -o -name '*.tsx' \) -print -quit | grep -q .; then
          basename "${d}"
        fi
      done | sort
)

for module in "${ON_DISK[@]}"; do
  if [[ -z "${DECLARED_STATE[${module}]:-}" ]]; then
    fail "registre : module '${module}' present sur disque mais absent de ${REGISTRY} — classer explicitement (layered|flat)"
  fi
done

for module in "${!DECLARED_STATE[@]}"; do
  if [[ ! -d "${MODULES_DIR}/${module}" ]]; then
    fail "registre : module '${module}' declare mais absent du disque — retirer la ligne"
  fi
done

# ---------------------------------------------------------------------------
# 4. Vérification réelle, module par module.
# ---------------------------------------------------------------------------

total_domain_files=0
total_layered_modules=0

for module in "${ON_DISK[@]}"; do
  state="${DECLARED_STATE[${module}]:-}"
  [[ -z "${state}" ]] && continue   # déjà signalé ci-dessus

  mapfile -t domain_files < <(
    find "${MODULES_DIR}/${module}" -type f \( -name '*.ts' -o -name '*.tsx' \) \
      | grep -E '/domain/' | grep -v '/__tests__/' | sort || true
  )

  count="${#domain_files[@]}"

  if [[ "${state}" == "layered" ]]; then
    if [[ "${count}" -eq 0 ]]; then
      fail "${module} : declare 'layered' mais aucun fichier sous **/domain/ — renommage silencieux ou registre obsolete"
      continue
    fi
    total_layered_modules=$((total_layered_modules + 1))
    total_domain_files=$((total_domain_files + count))
    note "${module} : layered, ${count} fichier(s) de domaine"

    for f in "${domain_files[@]}"; do
      while IFS= read -r violation; do
        [[ -z "${violation}" ]] && continue
        fail "${violation%%|*} : ${violation#*|}"
      done < <(analyse_file "${f}")
    done
  else
    # `flat` : la garde ne vérifie rien, mais l'état doit être vrai.
    if [[ "${count}" -gt 0 ]]; then
      fail "${module} : declare 'flat' mais ${count} fichier(s) sous **/domain/ — reclasser en 'layered'"
    fi
    note "${module} : flat (aucun domain/, dette declaree)"
  fi
done

# ---------------------------------------------------------------------------
# 5. Bilan.
# ---------------------------------------------------------------------------

autotest

if [[ "${failures}" -gt 0 ]]; then
  echo "" >&2
  echo "ECHEC — ${failures} probleme(s) de couche web." >&2
  echo "Voir ADR-0035 et ${REGISTRY}." >&2
  exit 1
fi

echo "OK — garde de couche web : ${total_layered_modules} module(s) layered, ${total_domain_files} fichier(s) de domaine verifie(s), ${#ON_DISK[@]} module(s) au registre."
