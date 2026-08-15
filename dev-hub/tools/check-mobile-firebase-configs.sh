#!/usr/bin/env bash
#
# Garde CI — Stubs Firebase mobiles cohérents (issue #3152, audit 2026-08-15).
#
# Contexte : google-services.json / GoogleService-Info.plist versionnés sont des
# STUBS à clés factices pour les builds locaux/forks (cf. mobile-apps-ci.yml
# « google-services.json n'est PLUS committé… stub à clés factices versionné ») ;
# les vraies clés sont restaurées depuis GOOGLE_SERVICES_JSON au build.
#
# Cette garde échoue quand un stub dérive silencieusement :
#   1. package_name (Android) / BUNDLE_ID (iOS) ne correspond pas à l'identité
#      réelle de l'app → le fichier installé au build est refusé ou pousse un
#      app id d'une autre app (push silencieusement inopérant).
#   2. Une vraie clé API ressemblante (non-stub) est commitée → fuite.
#   3. Un stub attendu est absent (build local cassé sans le secret).
#
# Usage : bash dev-hub/tools/check-mobile-firebase-configs.sh
# Exit 0 = stubs cohérents ; exit 1 = violation(s).

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
MOBILE="${ROOT}/front/mobile_apps"

# Identité réelle par app (source : android/app/build.gradle.kts namespace et
# ios/Runner.xcodeproj PRODUCT_BUNDLE_IDENTIFIER).
declare -A PACKAGES=(
  [leopardo_employee]="com.leopardo.employee"
  [leopardo_manager]="com.leopardo.manager"
  [leopardo_hr]="com.leopardo.rh"
  [leopardo_platform_admin]="com.leopardo.platformadmin"
)

# Valeurs de stub (factices) autorisées — ne jamais y voir une vraie clé.
STUB_APP_ID="1:000000000000:android:0000000000000000000000"
STUB_API_KEY="REDACTED_GOOGLE_API_KEY"
# Motifs de « vraies » clés (non-stub) qui ne doivent jamais être commitées.
# La valeur de stub versionnée (AIzaSyREPLACE_WITH_REAL_FIREBASE_KEY_0000) est
# explicitement autorisée ; toute AUTRE clé AIza… est une fuite.
STUB_API_KEY_ANDROID="AIzaSyREPLACE_WITH_REAL_FIREBASE_KEY_0000"
REAL_KEY_PATTERN='AIza[0-9A-Za-z_-]{20,}'

has_real_key() {
  # $1 = fichier. Retourne 0 si une clé AIza… autre que le stub est présente.
  grep -Eo "${REAL_KEY_PATTERN}" "$1" 2>/dev/null | grep -Fv "${STUB_API_KEY_ANDROID}" | grep -q .
}

failures=0

for app in "${!PACKAGES[@]}"; do
  expected="${PACKAGES[$app]}"
  json="${MOBILE}/${app}/android/app/google-services.json"
  plist="${MOBILE}/${app}/ios/Runner/GoogleService-Info.plist"

  # ── Android stub ──────────────────────────────────────────────────────────
  if [[ -f "${json}" ]]; then
    if ! grep -q "\"mobilesdk_app_id\"" "${json}"; then
      echo "FAIL ${app}: google-services.json sans mobilesdk_app_id"
      failures=$((failures + 1))
    fi
    if grep -q '"mobilesdk_app_id"' "${json}" && ! grep -q "${STUB_APP_ID}" "${json}"; then
      echo "FAIL ${app}: google-services.json porte un app id NON-STUB — seule la valeur stub ${STUB_APP_ID} est versionnée (vraies clés via GOOGLE_SERVICES_JSON)."
      failures=$((failures + 1))
    fi
    pkg="$(grep -m1 '"package_name"' "${json}" | sed 's/.*"package_name"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/')"
    if [[ -z "${pkg}" || "${pkg}" != "${expected}" ]]; then
      echo "FAIL ${app}: google-services.json package_name='${pkg:-<vide>}' attendu '${expected}'"
      failures=$((failures + 1))
    fi
    if has_real_key "${json}"; then
      echo "FAIL ${app}: google-services.json contient une clé API réelle hors stub (fuite potentielle)"
      failures=$((failures + 1))
    fi
  else
    echo "FAIL ${app}: stub android/app/google-services.json absent — builds locaux cassés sans GOOGLE_SERVICES_JSON"
    failures=$((failures + 1))
  fi

  # ── iOS stub ──────────────────────────────────────────────────────────────
  if [[ -f "${plist}" ]]; then
    key="$(sed -n '/<key>API_KEY<\/key>/,/<\/string>/p' "${plist}" | grep -m1 '<string>' | sed 's/.*<string>\(.*\)<\/string>.*/\1/')"
    if [[ "${key}" != "${STUB_API_KEY}" ]]; then
      echo "FAIL ${app}: GoogleService-Info.plist API_KEY n'est pas la valeur stub (${key:0:12}…) — seule la valeur stub ${STUB_API_KEY} est versionnée."
      failures=$((failures + 1))
    fi
    bundle="$(sed -n '/<key>BUNDLE_ID<\/key>/,/<\/string>/p' "${plist}" | grep -m1 '<string>' | sed 's/.*<string>\(.*\)<\/string>.*/\1/')"
    if [[ "${bundle}" != "${expected}" ]]; then
      echo "FAIL ${app}: GoogleService-Info.plist BUNDLE_ID='${bundle}' attendu '${expected}'"
      failures=$((failures + 1))
    fi
    if has_real_key "${plist}"; then
      echo "FAIL ${app}: GoogleService-Info.plist contient une clé API réelle hors stub (fuite potentielle)"
      failures=$((failures + 1))
    fi
  else
    echo "FAIL ${app}: stub ios/Runner/GoogleService-Info.plist absent"
    failures=$((failures + 1))
  fi
done

if [[ "${failures}" -gt 0 ]]; then
  echo "check-mobile-firebase-configs: ${failures} violation(s) — stubs mobiles incohérents (issue #3152)."
  exit 1
fi

echo "check-mobile-firebase-configs: stubs Firebase des 4 apps cohérents (package/BUNDLE_ID/stub)."
