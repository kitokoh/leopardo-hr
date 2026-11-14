#!/usr/bin/env bash
# ============================================================================
# check-mobile-android-sdk-versions.sh - compileSdk / minSdk EXPLICITES
# (issue #9491, suite de #9471)
#
# Deux pannes de `Build Debug` (13 et 14/11/2026) sont venues de la MEME ligne
# implicite dans les 11 apps : `compileSdk = flutter.compileSdkVersion` (36) et
# `minSdk = flutter.minSdkVersion` (24), alors que `flutter_local_notifications`
# 23 / `androidx.core` 1.19 exigent `minCompileSdk 37` et que `sentry_flutter`
# (via `leopardo_core`, donc pour TOUTES les apps) exige `minSdk 26`.
#
# Cette garde impose les deux valeurs en LITTERAL, refuse les formes `flutter.*`
# (une valeur implicite bouge sans PR, cf. #9432/#9434), et signale les alias
# Groovy historiques `compileSdkVersion` / `minSdkVersion`.
#
# ANCRAGE DES NOMS (corrige le 2026-11-14) : la detection cherchait le prefixe
# `minSdk` sans borne, donc `minSdkVersion = 26` etait lu `minSdk` + "Version"
# et la garde annoncait `minSdk = Version, attendu 26`. Le nom est desormais
# ancre : il doit etre suivi de `=` ou d'un espace, jamais d'une lettre.
#
# Usage :
#   bash dev-hub/tools/check-mobile-android-sdk-versions.sh [racine]
#   bash dev-hub/tools/check-mobile-android-sdk-versions.sh --self-test
#
# Sortie ASCII uniquement (console Windows cp1252, lecon #5875).
# Exit 0 = conforme ; exit 1 = violation(s).
# ============================================================================
set -euo pipefail

EXPECTED_COMPILE_SDK=37
EXPECTED_MIN_SDK=26

errors=0

fail() {
  echo "[android-sdk-versions] KO: $*" >&2
  errors=$((errors + 1))
}

# find_decl <fichier> <regex-du-nom> -> "nom=valeur" de la PREMIERE declaration.
#
# ANCRAGE : le nom doit etre suivi soit de `=` (espaces optionnels), soit d'au
# moins un espace. Consequences voulues :
#   - `minSdkVersion = 26` garde son nom COMPLET `minSdkVersion` ;
#   - `minSdk 26` (Groovy sans `=`) est capture aussi ;
#   - `// minSdk = 26` ne matche pas : la ligne doit COMMENCER par le nom ;
#   - `minSdkFoo = 1` ne matche pas : apres le nom il faut `=` ou un espace.
find_decl() {
  # Python plutot que sed : une regex BRE imbriquee a deja produit un message
  # trompeur (`minSdk = Version`). Ici l'ancrage est explicite, sans regex.
  python3 - "$1" "$2" <<'PYDECL'
import sys

path, name = sys.argv[1], sys.argv[2]
for raw in open(path, encoding="utf-8", errors="replace"):
    line = raw.strip()
    if not line or line.startswith("//"):
        continue
    for candidate in (name, name + "Version"):
        if not line.startswith(candidate):
            continue
        rest = line[len(candidate):]
        # ANCRAGE : apres le nom il faut "=" ou un espace, jamais une lettre.
        # `minSdkFoo = 1` ne matche donc pas, et `minSdkVersion = 26` garde son
        # nom complet au lieu d'etre lu comme `minSdk` + "Version".
        head = rest[:1]
        if not (head == "=" or (head != "" and head.isspace())):
            continue
        rest = rest.strip()
        if rest.startswith("="):
            rest = rest[1:].strip()
        parts = rest.split()
        value = parts[0] if parts else ""
        print(candidate + "=" + value)
        raise SystemExit(0)
print("")
PYDECL
}

# check_one <app> <declaration> <nom-canonique> <attendu> <rappel>
check_one() {
  local app="$1" decl="$2" canonical="$3" expected="$4" hint="$5"
  local name value

  if [[ -z "${decl}" ]]; then
    fail "${app}: ${canonical} absent. Declarer \`${canonical} = ${expected}\` en litteral : ${hint}"
    return
  fi
  name="${decl%%=*}"
  value="${decl#*=}"

  # Valeur implicite (flutter.*) : refusee meme si elle vaut aujourd'hui la
  # bonne valeur, parce qu'elle bouge sans PR.
  case "${value}" in
    *flutter.*|*"${canonical}Version"*)
      fail "${app}: ${canonical} implicite (\`${value}\`). Declarer \`${canonical} = ${expected}\` en litteral : ${hint}"
      return
      ;;
  esac

  # Alias Groovy historique : signale comme alias, avec sa valeur reelle -
  # jamais comme une valeur tronquee (`minSdk = Version`).
  if [[ "${name}" != "${canonical}" ]]; then
    fail "${app}: \`${name}\` est l'alias Groovy historique de \`${canonical}\` (deprecie) : ecrire \`${canonical} = ${expected}\`. Valeur lue : ${value}."
    return
  fi

  if [[ -z "${value}" ]]; then
    fail "${app}: ${canonical} sans valeur. Declarer \`${canonical} = ${expected}\`."
  elif [[ "${value}" != "${expected}" ]]; then
    fail "${app}: ${canonical} = ${value}, attendu ${expected}."
  fi
}

check_app() {
  local app_dir="$1"
  local app
  app="$(basename "${app_dir}")"

  local build=""
  if [[ -f "${app_dir}/android/app/build.gradle.kts" ]]; then
    build="${app_dir}/android/app/build.gradle.kts"
  elif [[ -f "${app_dir}/android/app/build.gradle" ]]; then
    build="${app_dir}/android/app/build.gradle"
  else
    fail "${app}: aucun android/app/build.gradle(.kts) - une app sans projet Android ne peut pas etre buildee, et echappe donc silencieusement a cette garde."
    return
  fi

  check_one "${app}" "$(find_decl "${build}" 'compileSdk')" \
    compileSdk "${EXPECTED_COMPILE_SDK}" \
    "la valeur de Flutter est en retard sur les AAR du depot (minCompileSdk 37)."
  check_one "${app}" "$(find_decl "${build}" 'minSdk')" \
    minSdk "${EXPECTED_MIN_SDK}" \
    "sentry_flutter (via leopardo_core) exige 26, et la contrainte traverse la dependance de package."
}

check_root() {
  local root="$1"
  local apps_parent="${root}/front/mobile_apps"

  if [[ ! -d "${apps_parent}" ]]; then
    fail "front/mobile_apps introuvable sous ${root} - la garde ne peut rien verifier."
    return
  fi

  local seen=0
  local app_dir app
  for app_dir in "${apps_parent}"/*/; do
    [[ -f "${app_dir}pubspec.yaml" ]] || continue
    app="$(basename "${app_dir}")"
    [[ "${app}" == "leopardo_core" ]] && continue
    seen=$((seen + 1))
    check_app "${app_dir%/}"
  done

  if [[ ${seen} -eq 0 ]]; then
    fail "aucune app mobile detectee sous ${apps_parent} - garde fail-closed (une liste vide ne prouve rien)."
  fi
}

self_test() {
  local tmp
  tmp="$(mktemp -d)"
  trap 'rm -rf "${tmp:-}"' EXIT

  mkapp() { # mkapp <racine> <nom> <compileSdk> <minSdk>
    local root="$1" name="$2" csdk="$3" msdk="$4"
    mkdir -p "${root}/front/mobile_apps/${name}/android/app"
    : > "${root}/front/mobile_apps/${name}/pubspec.yaml"
    cat > "${root}/front/mobile_apps/${name}/android/app/build.gradle.kts" <<KTS
android {
    compileSdk = ${csdk}
    defaultConfig {
        minSdk = ${msdk}
    }
}
KTS
  }

  mkapp_groovy() { # mkapp_groovy <racine> <nom> <ligne-minSdk>
    local root="$1" name="$2" min_line="$3"
    mkdir -p "${root}/front/mobile_apps/${name}/android/app"
    : > "${root}/front/mobile_apps/${name}/pubspec.yaml"
    cat > "${root}/front/mobile_apps/${name}/android/app/build.gradle" <<GROOVY
android {
    compileSdk 37
    defaultConfig {
        ${min_line}
    }
}
GROOVY
  }

  # expect <pass|fail> <libelle> <racine> [sous-chaine-attendue] [sous-chaine-interdite]
  expect() {
    local want="$1" label="$2" root="$3"
    local want_msg="${4:-}" forbid_msg="${5:-}"
    local out rc=0
    # `check_root` accumule dans `errors` et sort toujours 0 : on lit le
    # compteur DANS LE MEME SHELL (un sous-shell perdrait l'increment).
    errors=0
    # Sortie dans un FICHIER, pas en substitution de commande : `$(...)` ouvre
    # un sous-shell, donc les `errors=$((errors+1))` de `check_root` seraient
    # perdus et tous les cas passeraient pour verts.
    local errfile="${tmp}/_expect_out"
    check_root "${root}" >"${errfile}" 2>&1 || true
    out="$(cat "${errfile}")"
    [[ ${errors} -eq 0 ]] || rc=1

    if [[ "${want}" == "pass" && ${rc} -ne 0 ]]; then
      echo "[android-sdk-versions --self-test] KO: ${label} aurait du etre VERT." >&2
      echo "${out}" >&2
      return 1
    fi
    if [[ "${want}" == "fail" && ${rc} -eq 0 ]]; then
      echo "[android-sdk-versions --self-test] KO: ${label} aurait du etre ROUGE." >&2
      return 1
    fi
    if [[ -n "${want_msg}" && "${out}" != *"${want_msg}"* ]]; then
      echo "[android-sdk-versions --self-test] KO: ${label} - message attendu absent." >&2
      echo "${out}" >&2
      return 1
    fi
    if [[ -n "${forbid_msg}" && "${out}" == *"${forbid_msg}"* ]]; then
      echo "[android-sdk-versions --self-test] KO: ${label} - message parasite present." >&2
      echo "${out}" >&2
      return 1
    fi
    return 0
  }

  # 1. cas sain (kotlin DSL, leopardo_core ignore car c'est un package)
  mkapp "${tmp}/ok" leopardo_employee 37 26
  mkdir -p "${tmp}/ok/front/mobile_apps/leopardo_core"
  : > "${tmp}/ok/front/mobile_apps/leopardo_core/pubspec.yaml"
  expect pass "etat conforme (37 / 26, leopardo_core ignore)" "${tmp}/ok"

  # 2. l'ANCIEN etat du depot (valeurs implicites Flutter)
  mkdir -p "${tmp}/old/front/mobile_apps/leopardo_pos_retail/android/app"
  : > "${tmp}/old/front/mobile_apps/leopardo_pos_retail/pubspec.yaml"
  cat > "${tmp}/old/front/mobile_apps/leopardo_pos_retail/android/app/build.gradle.kts" <<'KTS'
android {
    compileSdk = flutter.compileSdkVersion
    defaultConfig {
        minSdk = flutter.minSdkVersion
    }
}
KTS
  expect fail "ancien etat (flutter.compileSdkVersion / flutter.minSdkVersion)" "${tmp}/old" "implicite"

  # 3. compileSdk trop bas
  mkapp "${tmp}/csdk" leopardo_hr 36 26
  expect fail "compileSdk = 36" "${tmp}/csdk" "compileSdk = 36, attendu 37"

  # 4. minSdk trop bas
  mkapp "${tmp}/msdk" leopardo_pos_restaurant 37 24
  expect fail "minSdk = 24" "${tmp}/msdk" "minSdk = 24, attendu 26"

  # 5. app sans projet Android (echappe a la garde)
  mkdir -p "${tmp}/noandroid/front/mobile_apps/leopardo_new"
  : > "${tmp}/noandroid/front/mobile_apps/leopardo_new/pubspec.yaml"
  mkapp "${tmp}/noandroid" leopardo_manager 37 26
  expect fail "app sans android/app/build.gradle" "${tmp}/noandroid" "aucun android/app/build.gradle"

  # 6. aucune app detectee (liste vide)
  mkdir -p "${tmp}/empty/front/mobile_apps"
  expect fail "arbre sans app (fail-closed)" "${tmp}/empty" "aucune app mobile detectee"

  # 7. Groovy : alias historique AVEC `=` et la BONNE valeur (26). Le nom
  #    complet doit etre reconnu -> message d'alias, et surtout PAS un
  #    `minSdk = Version` tronque (regression corrigee ici).
  mkapp_groovy "${tmp}/groovy_eq" leopardo_travel_agent "minSdkVersion = 26"
  expect fail "Groovy minSdkVersion = 26" "${tmp}/groovy_eq" \
    "alias Groovy historique de \`minSdk\`" "minSdk = Version"

  # 8. Groovy : alias historique SANS `=` (forme pre-AGP 7)
  mkapp_groovy "${tmp}/groovy_sp" leopardo_accounting "minSdkVersion 24"
  expect fail "Groovy minSdkVersion 24" "${tmp}/groovy_sp" \
    "alias Groovy historique de \`minSdk\`" "minSdk = Version"

  echo "[android-sdk-versions --self-test] OK: 8 cas (1 vert, 7 rouges) verifies."
}

if [[ "${1:-}" == "--self-test" ]]; then
  self_test
else
  root="${1:-$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)}"
  check_root "${root}"
  if [[ ${errors} -gt 0 ]]; then
    echo "[android-sdk-versions] ${errors} probleme(s) - voir ci-dessus (issue #9491)." >&2
    exit 1
  fi
  echo "[android-sdk-versions] OK: compileSdk = ${EXPECTED_COMPILE_SDK} et minSdk = ${EXPECTED_MIN_SDK} explicites dans toutes les apps de front/mobile_apps."
fi
