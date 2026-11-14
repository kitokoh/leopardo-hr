#!/usr/bin/env bash
# ============================================================================
# check-mobile-android-sdk-versions.sh - compileSdk / minSdk EXPLICITES
# (issue #9491, suite de #9471)
#
# Le 2026-11-14, la CI mobile est passee de 11 echecs a 11 succes en deux temps,
# et chaque etape a ete payee par une PR : d'abord le toolchain (#9467), puis le
# `compileSdk` (#9472). Les deux defauts venaient de la MEME ligne implicite :
#
#   compileSdk = flutter.compileSdkVersion   # = 36 sur Flutter 3.50
#   minSdk     = flutter.minSdkVersion       # = 24
#
# Or `flutter_local_notifications` 23 et `androidx.core` 1.19 declarent
# `minCompileSdk 37`, et `sentry_flutter` (via `leopardo_core`, donc pour TOUTES
# les apps) declare `minSdkVersion 26`. Les valeurs par defaut de Flutter sont
# donc en retard sur les dependances du depot, et le manquement ne se voit
# qu'APRES ~4 minutes de build Gradle, app par app.
#
# Cette garde rend le defaut impossible a reintroduire :
#   1. chaque app (`front/mobile_apps/*/pubspec.yaml`, hors `leopardo_core`
#      qui est un package et n'a pas de projet Android) doit avoir
#      `android/app/build.gradle` ou `android/app/build.gradle.kts` ;
#   2. ce fichier doit declarer `compileSdk` = 37 et `minSdk` = 26 en LITTERAL ;
#   3. `flutter.compileSdkVersion` / `flutter.minSdkVersion` sont refuses : une
#      valeur implicite peut changer sous nos pieds a chaque montee de Flutter,
#      y compris via `pub upgrade` (#9432/#9434 ont deja paye ce prix).
#
# Usage :
#   bash dev-hub/tools/check-mobile-android-sdk-versions.sh [racine]
#   bash dev-hub/tools/check-mobile-android-sdk-versions.sh --self-test
#
# Sortie volontairement ASCII : ce script doit pouvoir s'executer sans planter
# sur une console Windows cp1252 (lecon #5875 / threat-models).
# Exit 0 = conforme ; exit 1 = violation(s).
# ============================================================================
set -euo pipefail

# Source unique des valeurs attendues : une montee de SDK se fait ICI, en
# meme temps que les build.gradle et le commentaire de la PR.
EXPECTED_COMPILE_SDK=37
EXPECTED_MIN_SDK=26

errors=0

fail() {
  echo "[android-sdk-versions] KO: $*" >&2
  errors=$((errors + 1))
}

# Extrait la valeur d'une propriete Gradle (kotlin DSL `compileSdk = 37` ou
# groovy `compileSdk 37`), en tolerant CRLF et l'indentation.
read_prop() {
  local file="$1" key="$2"
  sed -n "s/^[[:space:]]*${key}[[:space:]]*=\{0,1\}[[:space:]]*\([^[:space:]\r]*\).*/\1/p" \
    "${file}" | head -n 1 | tr -d '\r'
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

  local compile_sdk min_sdk
  compile_sdk="$(read_prop "${build}" compileSdk)"
  min_sdk="$(read_prop "${build}" minSdk)"

  if [[ -z "${compile_sdk}" ]]; then
    compile_sdk="$(read_prop "${build}" compileSdkVersion)"
  fi
  if [[ -z "${min_sdk}" ]]; then
    min_sdk="$(read_prop "${build}" minSdkVersion)"
  fi

  # 1. compileSdk
  if [[ -z "${compile_sdk}" ]]; then
    fail "${app}: compileSdk absent de $(basename "${build}")."
  elif [[ "${compile_sdk}" == *"compileSdkVersion"* ]]; then
    fail "${app}: compileSdk implicite (\`${compile_sdk}\`). Declarer \`compileSdk = ${EXPECTED_COMPILE_SDK}\` en litteral : la valeur de Flutter est en retard sur les AAR du depot (minCompileSdk 37)."
  elif [[ "${compile_sdk}" != "${EXPECTED_COMPILE_SDK}" ]]; then
    fail "${app}: compileSdk = ${compile_sdk}, attendu ${EXPECTED_COMPILE_SDK}."
  fi

  # 2. minSdk
  if [[ -z "${min_sdk}" ]]; then
    fail "${app}: minSdk absent de $(basename "${build}")."
  elif [[ "${min_sdk}" == *"minSdkVersion"* ]]; then
    fail "${app}: minSdk implicite (\`${min_sdk}\`). Declarer \`minSdk = ${EXPECTED_MIN_SDK}\` en litteral : sentry_flutter (via leopardo_core) exige 26, et la contrainte traverse la dependance de package."
  elif [[ "${min_sdk}" != "${EXPECTED_MIN_SDK}" ]]; then
    fail "${app}: minSdk = ${min_sdk}, attendu ${EXPECTED_MIN_SDK}."
  fi
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

  expect() { # expect <attendu> <libelle> <racine>
    local want="$1" label="$2" root="$3" rc=0
    # `check_root` accumule dans `errors` et sort toujours 0 : on lit le
    # compteur DANS LE MEME SHELL (un sous-shell perdrait l'increment).
    errors=0
    check_root "${root}" >/dev/null 2>&1 || true
    [[ ${errors} -eq 0 ]] || rc=1
    if [[ "${want}" == "pass" && ${rc} -ne 0 ]]; then
      echo "[android-sdk-versions --self-test] KO: ${label} aurait du etre VERT." >&2
      return 1
    fi
    if [[ "${want}" == "fail" && ${rc} -eq 0 ]]; then
      echo "[android-sdk-versions --self-test] KO: ${label} aurait du etre ROUGE." >&2
      return 1
    fi
    return 0
  }

  # cas sain
  mkapp "${tmp}/ok" leopardo_employee 37 26
  mkdir -p "${tmp}/ok/front/mobile_apps/leopardo_core"
  : > "${tmp}/ok/front/mobile_apps/leopardo_core/pubspec.yaml"
  expect pass "etat conforme (37 / 26, leopardo_core ignore)" "${tmp}/ok"

  # mutation 1 : l'ANCIEN etat du depot (valeurs implicites Flutter)
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
  expect fail "ancien etat (flutter.compileSdkVersion / flutter.minSdkVersion)" "${tmp}/old"

  # mutation 2 : compileSdk trop bas
  mkapp "${tmp}/csdk" leopardo_hr 36 26
  expect fail "compileSdk = 36" "${tmp}/csdk"

  # mutation 3 : minSdk trop bas
  mkapp "${tmp}/msdk" leopardo_pos_restaurant 37 24
  expect fail "minSdk = 24" "${tmp}/msdk"

  # mutation 4 : app sans projet Android (echappe a la garde)
  mkdir -p "${tmp}/noandroid/front/mobile_apps/leopardo_new"
  : > "${tmp}/noandroid/front/mobile_apps/leopardo_new/pubspec.yaml"
  mkapp "${tmp}/noandroid" leopardo_manager 37 26
  expect fail "app sans android/app/build.gradle" "${tmp}/noandroid"

  # mutation 5 : aucune app detectee (liste vide)
  mkdir -p "${tmp}/empty/front/mobile_apps"
  expect fail "arbre sans app (fail-closed)" "${tmp}/empty"

  echo "[android-sdk-versions --self-test] OK: 5 cas (1 vert, 4 rouges) verifies."
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
