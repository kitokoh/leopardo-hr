#!/usr/bin/env bash
# ============================================================================
# Test de la garde compileSdk/minSdk explicites (#9491).
#
# Une garde n'existe que si on l'a vue ROUGE : ce test verifie les deux sens sur
# l'ANCIEN etat reel du depot (les build.gradle.kts d'avant #9472, qui portaient
# `flutter.compileSdkVersion` / `flutter.minSdkVersion`), puis verifie que la
# garde est effectivement branchee dans un workflow - une garde jamais appelee
# ne protege rien (lecon #8013/#8024).
#
# Aucun reseau, aucun binaire externe hors bash/sed/mktemp.
# Sortie ASCII uniquement (console Windows cp1252, lecon #5875).
# ============================================================================
set -euo pipefail

here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
root="$(cd "${here}/../../.." && pwd)"
guard="${here}/../check-mobile-android-sdk-versions.sh"
workflow="${root}/.github/workflows/architecture-check.yml"

fail() {
  echo "[mobile-android-sdk-versions.test] KO: $*" >&2
  exit 1
}

[[ -f "${guard}" ]] || fail "garde introuvable : ${guard}"

# 1. L'auto-test interne de la garde (cas sain + 4 mutations, dont l'ancien etat).
if ! out="$(bash "${guard}" --self-test 2>&1)"; then
  echo "${out}" >&2
  fail "l'auto-test de la garde echoue."
fi
[[ "${out}" == *"OK: 5 cas"* ]] || fail "auto-test incomplet : ${out}"

# 2. Vert sur le depot courant (les 11 apps sont en 37 / 26 depuis #9471).
if ! out="$(bash "${guard}" "${root}" 2>&1)"; then
  echo "${out}" >&2
  fail "la garde est ROUGE sur le depot courant."
fi
[[ "${out}" == *"OK: compileSdk = 37"* ]] || fail "sortie verte inattendue : ${out}"

# 3. Rouge sur l'ANCIEN etat : on reprend un vrai fichier du depot et on remet
#    les deux lignes implicites d'avant #9471.
tmp="$(mktemp -d)"
trap 'rm -rf "${tmp}"' EXIT

mkdir -p "${tmp}/front/mobile_apps/leopardo_pos_retail/android/app"
: > "${tmp}/front/mobile_apps/leopardo_pos_retail/pubspec.yaml"
src="${root}/front/mobile_apps/leopardo_pos_retail/android/app/build.gradle.kts"
[[ -f "${src}" ]] || fail "fichier de reference introuvable : ${src}"
sed -e 's/^\([[:space:]]*\)compileSdk = 37/\1compileSdk = flutter.compileSdkVersion/' \
    -e 's/^\([[:space:]]*\)minSdk = 26/\1minSdk = flutter.minSdkVersion/' \
    "${src}" > "${tmp}/front/mobile_apps/leopardo_pos_retail/android/app/build.gradle.kts"

if bash "${guard}" "${tmp}" >/dev/null 2>&1; then
  fail "l'ancien etat (flutter.compileSdkVersion / flutter.minSdkVersion) n'est PAS detecte."
fi

# 4. Rouge sur une valeur trop basse, sur un fichier reel.
sed -e 's/^\([[:space:]]*\)compileSdk = 37/\1compileSdk = 36/' \
    "${src}" > "${tmp}/front/mobile_apps/leopardo_pos_retail/android/app/build.gradle.kts"
if bash "${guard}" "${tmp}" >/dev/null 2>&1; then
  fail "compileSdk = 36 n'est PAS detecte."
fi

# 5. La garde est branchee dans la CI (sinon elle ne mesure rien).
[[ -f "${workflow}" ]] || fail "workflow introuvable : ${workflow}"
grep -q "check-mobile-android-sdk-versions.sh" "${workflow}" \
  || fail "la garde n'est appelee par aucun job de architecture-check.yml."
grep -q "tests/check-mobile-android-sdk-versions.test.sh" "${workflow}" \
  || fail "le test de la garde n'est pas execute par la CI."

echo "MOBILE_ANDROID_SDK_VERSIONS_GUARD_SELF_TEST_OK"
