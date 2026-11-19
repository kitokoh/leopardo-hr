#!/usr/bin/env bash
#
# check-mobile-layer-guard.sh — garde de couche des apps mobiles (#9645, #9646).
#
# ## Pourquoi ce script existe
#
# ADR-0035 fait de la découpe en couches la norme des apps mobiles grand
# public, et `leopardo_public/test/architecture/layer_guard_test.dart` vérifie
# la règle au plus près du code. Mais ce test :
#
#   - ne tourne que si `flutter test` tourne sur `leopardo_public` ;
#   - ne peut pas couvrir les 11 autres apps sans dupliquer le fichier ;
#   - est invisible du job `mobile-apps-guard`, seul job INCONDITIONNEL de la
#     CI mobile (les jobs Flutter sont gatés par `paths-filter`).
#
# Ce script est l'équivalent partagé : il tourne en secondes, sans Flutter, et
# couvre toutes les apps d'un coup.
#
# ## Ce qu'il refuse
#
# Dans tout `lib/features/**/domain/` :
#   - un import `package:` (framework, état, réseau, UI partagée) ;
#   - un import d'une couche supérieure (`application/`, `infrastructure/`,
#     `components/`, `screens/`, `widgets/`, `data/`, `models/`, `providers/`,
#     `services/`).
#
# ## Ce qu'il refuse AUSSI, et qui est le vrai apport
#
# Le registre `mobile-layer-registry.txt` déclare l'état de chaque app. Sans
# lui, la garde serait fail-open sur les apps futures : une app créée sans
# `domain/` passerait au vert sans que personne n'ait décidé qu'elle serait
# plate. Le registre rend la dette visible et la rend impossible à grossir en
# silence.
#
#   - app sur disque absente du registre        → échec
#   - app du registre absente du disque         → échec
#   - `layered` sans aucun `domain/`            → échec (renommage silencieux)
#   - `flat` avec un `domain/`                  → échec (le registre ment)
#
# ## Auto-test
#
# Le script soumet une source fautive à son propre analyseur et échoue si elle
# n'est pas signalée. Sans cela, une regex cassée passerait au vert pour
# toujours — c'est exactement le défaut que l'assertion « trouve au moins un
# fichier de domaine » corrige côté Dart.
#
# Usage :
#   bash dev-hub/tools/check-mobile-layer-guard.sh
#   bash dev-hub/tools/check-mobile-layer-guard.sh --verbose
#
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "${ROOT}"

APPS_DIR="front/mobile_apps"
REGISTRY="dev-hub/tools/mobile-layer-registry.txt"
VERBOSE=0
[[ "${1:-}" == "--verbose" ]] && VERBOSE=1

failures=0
fail() { echo "::error::$*" >&2; failures=$((failures + 1)); }
note() { [[ "${VERBOSE}" -eq 1 ]] && echo "  $*" || true; }

if [[ ! -d "${APPS_DIR}" ]]; then
  echo "OK — ${APPS_DIR} absent (garde sans objet)."
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
  '/screens/'
  '/widgets/'
  '/data/'
  '/models/'
  '/providers/'
  '/services/'
)

# Imprime une violation par ligne pour un fichier donné.
# Sortie : "<fichier>|<raison>"
analyse_file() {
  local file="$1"
  local specifier reason

  # `import '...'` et `export '...'`, y compris les directives multi-lignes
  # (`import\n  'package:x';`) que le formateur Dart produit sur les longues
  # URI. On lit donc le fichier entier et on laisse la regex traverser les
  # retours à la ligne entre le mot-clé et la chaîne.
  while IFS= read -r specifier; do
    [[ -z "${specifier}" ]] && continue

    if [[ "${specifier}" == package:* ]]; then
      echo "${file}|package interdit dans domain/ : ${specifier}"
      continue
    fi

    # Seuls les imports relatifs peuvent désigner une couche du même feature.
    [[ "${specifier}" != .* ]] && continue

    local normalized="${specifier//\\//}"
    for layer in "${FORBIDDEN_LAYERS[@]}"; do
      if [[ "${normalized}" == *"${layer}"* ]]; then
        echo "${file}|couche ${layer} importee depuis domain/ : ${specifier}"
      fi
    done
  done < <(perl -0777 -ne '
    while (/(?:^|\n)\s*(?:import|export)\s+["\x27]([^"\x27]+)["\x27]/g) {
      print "$1\n";
    }
  ' "${file}")
}

# ---------------------------------------------------------------------------
# 2. Auto-test — la garde doit détecter une violation qu'on lui soumet.
# ---------------------------------------------------------------------------

autotest() {
  local tmp
  tmp="$(mktemp)"
  cat > "${tmp}" <<'DART'
import 'package:material_ui/material_ui.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../infrastructure/surface_theme.dart';
import '../../other_feature/data/repository.dart';
import 'dart:math';
DART

  local out
  out="$(analyse_file "${tmp}")"
  rm -f "${tmp}"

  local pkg_count layer_count
  pkg_count="$(printf '%s\n' "${out}" | grep -c 'package interdit' || true)"
  layer_count="$(printf '%s\n' "${out}" | grep -c 'couche ' || true)"

  # 2 imports `package:` fautifs, 2 imports de couche supérieure fautifs.
  # `dart:math` ne doit produire AUCUNE ligne.
  if [[ "${pkg_count}" -ne 2 ]]; then
    fail "auto-test : ${pkg_count} import(s) package: detecte(s), 2 attendus — l'analyseur est casse"
  fi
  if [[ "${layer_count}" -ne 2 ]]; then
    fail "auto-test : ${layer_count} import(s) de couche superieure detecte(s), 2 attendus — l'analyseur est casse"
  fi
  if printf '%s\n' "${out}" | grep -q 'dart:math'; then
    fail "auto-test : 'dart:math' signale a tort — le SDK doit rester autorise"
  fi

  note "auto-test : ${pkg_count} package:, ${layer_count} couche(s) — attendu 2/2"
}

# ---------------------------------------------------------------------------
# 3. Registre — chaque app déclarée, et l'état déclaré doit être vrai.
# ---------------------------------------------------------------------------

declare -A DECLARED_STATE=()
declare -A DECLARED_LINE=()

while IFS= read -r line; do
  [[ -z "${line}" ]] && continue
  [[ "${line}" == \#* ]] && continue

  # shellcheck disable=SC2086
  set -- ${line}
  local_app="$1"
  local_state="$2"

  if [[ -z "${local_app:-}" || -z "${local_state:-}" ]]; then
    fail "registre : ligne malformee (attendu '<app> <etat> <note>') : ${line}"
    continue
  fi

  if [[ "${local_state}" != "layered" && "${local_state}" != "flat" ]]; then
    fail "registre : etat inconnu '${local_state}' pour ${local_app} (attendu layered|flat)"
    continue
  fi

  if [[ -n "${DECLARED_STATE[${local_app}]:-}" ]]; then
    fail "registre : ${local_app} declaree deux fois"
    continue
  fi

  DECLARED_STATE["${local_app}"]="${local_state}"
  DECLARED_LINE["${local_app}"]="${line}"
done < "${REGISTRY}"

# Apps réellement présentes (dossiers avec un pubspec.yaml).
mapfile -t ON_DISK < <(
  find "${APPS_DIR}" -mindepth 1 -maxdepth 1 -type d -print \
    | while read -r d; do
        [[ -f "${d}/pubspec.yaml" ]] && basename "${d}"
      done | sort
)

for app in "${ON_DISK[@]}"; do
  if [[ -z "${DECLARED_STATE[${app}]:-}" ]]; then
    fail "registre : app '${app}' presente sur disque mais absente de ${REGISTRY} — classer explicitement (layered|flat)"
  fi
done

for app in "${!DECLARED_STATE[@]}"; do
  if [[ ! -d "${APPS_DIR}/${app}" ]]; then
    fail "registre : app '${app}' declaree mais absente du disque — retirer la ligne"
  fi
done

# ---------------------------------------------------------------------------
# 4. Vérification réelle, app par app.
# ---------------------------------------------------------------------------

total_domain_files=0
total_layered_apps=0

for app in "${ON_DISK[@]}"; do
  state="${DECLARED_STATE[${app}]:-}"
  [[ -z "${state}" ]] && continue   # déjà signalé ci-dessus

  mapfile -t domain_files < <(
    find "${APPS_DIR}/${app}/lib" -type f -name '*.dart' 2>/dev/null \
      | grep -E '/domain/' | sort || true
  )

  count="${#domain_files[@]}"

  if [[ "${state}" == "layered" ]]; then
    if [[ "${count}" -eq 0 ]]; then
      fail "${app} : declaree 'layered' mais aucun fichier sous lib/**/domain/ — renommage silencieux ou registre obsolete"
      continue
    fi
    total_layered_apps=$((total_layered_apps + 1))
    total_domain_files=$((total_domain_files + count))
    note "${app} : layered, ${count} fichier(s) de domaine"

    for f in "${domain_files[@]}"; do
      while IFS= read -r violation; do
        [[ -z "${violation}" ]] && continue
        fail "${violation%%|*} : ${violation#*|}"
      done < <(analyse_file "${f}")
    done
  else
    # `flat` : la garde ne vérifie rien, mais l'état doit être vrai.
    if [[ "${count}" -gt 0 ]]; then
      fail "${app} : declaree 'flat' mais ${count} fichier(s) sous lib/**/domain/ — reclasser en 'layered'"
    fi
    note "${app} : flat (aucun domain/, dette declaree)"
  fi
done

# ---------------------------------------------------------------------------
# 5. Bilan.
# ---------------------------------------------------------------------------

autotest

if [[ "${failures}" -gt 0 ]]; then
  echo "" >&2
  echo "ECHEC — ${failures} probleme(s) de couche mobile." >&2
  echo "Voir ADR-0035 et ${REGISTRY}." >&2
  exit 1
fi

echo "OK — garde de couche mobile : ${total_layered_apps} app(s) layered, ${total_domain_files} fichier(s) de domaine verifie(s), ${#ON_DISK[@]} app(s) au registre."
