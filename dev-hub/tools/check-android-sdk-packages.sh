#!/usr/bin/env bash
# ============================================================================
# check-android-sdk-packages.sh — garde des paquets Android SDK demandés
# (issue #7519)
#
# Pourquoi : `.github/actions/setup-flutter-android` demandait
# `packages: 'tools platform-tools platforms;android-36 build-tools;36.0.0'`.
# Les runners GitHub embarquent cmdline-tools 12.0, que
# `android-actions/setup-android` juge « wrong version » et remplace par
# cmdline-tools **20.0**. Dans 20.0, le paquet hérité `tools` n'existe plus :
#
#   [command] .../cmdline-tools/20.0/bin/sdkmanager tools
#   Warning: Failed to find package 'tools'
#   Error: The process '.../sdkmanager' failed with exit code 1
#
# L'action traite ce code de sortie comme fatal → TOUTE l'étape « Setup
# Flutter + Java » échoue, donc pub get / build APK / distribution Firebase /
# analyse mobile / CodeQL sont `skipped`. Effet mesuré : `main` rouge
# (`Distribute {employee,hr,manager,platform_admin} to Firebase`) et toutes les
# PRs mobiles rouges, y compris les bumps Dependabot `pub` dont c'est le seul
# changement.
#
# La garde refuse un paquet qui n'existe pas dans cmdline-tools 20.0, avant que
# le runner ne le découvre à la place du développeur.
#
# Usage :
#   bash dev-hub/tools/check-android-sdk-packages.sh            # dépôt courant
#   bash dev-hub/tools/check-android-sdk-packages.sh <racine>
#   bash dev-hub/tools/check-android-sdk-packages.sh --self-test
# ============================================================================
set -euo pipefail

ACTION_PATH=".github/actions/setup-flutter-android/action.yml"

# Paquets connus de cmdline-tools 20.0 (allowlist stricte : tout le reste est
# refusé, un paquet inconnu faisant échouer `sdkmanager` en bloc).
is_known_package() {
  local pkg="$1"
  case "${pkg}" in
    platform-tools|emulator|cmdline-tools\;latest) return 0 ;;
    platforms\;android-[0-9]*) return 0 ;;
    build-tools\;[0-9]*.[0-9]*.[0-9]*) return 0 ;;
    *) return 1 ;;
  esac
}

errors=0

fail() {
  echo "::error::[android-sdk] $*" >&2
  errors=$((errors + 1))
}

check_action() {
  local root="$1"
  local action="${root}/${ACTION_PATH}"
  local before=${errors}
  [[ -f "${action}" ]] || { fail "action introuvable : ${ACTION_PATH}"; return 1; }

  # `packages:` peut être une chaîne unique ou un bloc multi-lignes : on
  # délègue l'extraction à Python (indentation YAML réelle, pas de regex
  # approximative — le premier jet consommait les étapes suivantes du fichier).
  local packages
  packages="$(python3 - "${action}" <<'PYEXTRACT'
import re, sys

path = sys.argv[1]
lines = open(path, encoding="utf-8").read().split("\n")
for i, line in enumerate(lines):
    m = re.match(r"^([ \t]*)packages:[ \t]*(.*)$", line)
    if not m:
        continue
    indent, inline = m.group(1), m.group(2).strip()
    if inline:
        print(inline.strip("'\""))
        sys.exit(0)
    items, base = [], None
    for follow in lines[i + 1:]:
        if not follow.strip():
            continue
        cur = len(follow) - len(follow.lstrip())
        if cur <= len(indent):
            break
        base = cur if base is None else base
        item = follow.strip()
        if item.startswith("- "):
            item = item[2:]
        items.append(item.strip("'\""))
    print(" ".join(items))
    sys.exit(0)
PYEXTRACT
)"
  if [[ -z "${packages// }" ]]; then
    fail "${ACTION_PATH} : aucun paquet Android déclaré — la garde ne peut plus vérifier (le runner installera-t-il platform 36 ?)."
    return 1
  fi

  local pkg
  for pkg in ${packages}; do
    if [[ "${pkg}" == "tools" ]]; then
      fail "${ACTION_PATH} : le paquet hérité « tools » est demandé. Il n'existe plus dans cmdline-tools 20.0 utilisé par les runners : \`sdkmanager tools\` sort en 1 et fait échouer toute la chaîne mobile (#7519). Retirer « tools » de packages:."
    elif ! is_known_package "${pkg}"; then
      fail "${ACTION_PATH} : paquet Android inconnu « ${pkg} » — un nom que \`sdkmanager\` ne sait pas résoudre fait échouer l'étape entière (#7519). Paquets acceptés : platform-tools, platforms;android-NN, build-tools;X.Y.Z, emulator, cmdline-tools;latest."
    fi
  done

  # Le cœur du besoin doit rester présent : compiler en compileSdk 36.
  if ! printf '%s' "${packages}" | grep -q 'platforms;android-36'; then
    fail "${ACTION_PATH} : platforms;android-36 absent — les apps compilent en compileSdk 36 (flutter.compileSdkVersion)."
  fi

  [[ ${errors} -eq ${before} ]] || return 1
  return 0
}

self_test() {
  local tmp
  tmp="$(mktemp -d)"
  trap 'rm -rf "${tmp:-}"' EXIT
  mkdir -p "${tmp}/.github/actions/setup-flutter-android"

  # cas sain : la liste corrigée par #7519
  cat > "${tmp}/${ACTION_PATH}" <<'YAML'
runs:
  using: "composite"
  steps:
    - name: Setup Android SDK (platform 36)
      uses: android-actions/setup-android@40fd30fb8d7440372e1316f5d1809ec01dcd3699 # v4.0.1
      with:
        packages: 'platform-tools platforms;android-36 build-tools;36.0.0'
YAML
  if ! check_action "${tmp}" >/dev/null 2>&1; then
    echo "::error::[android-sdk --self-test] la liste corrigée est refusée — garde trop stricte." >&2
    return 1
  fi

  # mutation 1 : retour du paquet hérité (le défaut #7519)
  cat > "${tmp}/${ACTION_PATH}" <<'YAML'
runs:
  using: "composite"
  steps:
    - name: Setup Android SDK (platform 36)
      uses: android-actions/setup-android@40fd30fb8d7440372e1316f5d1809ec01dcd3699 # v4.0.1
      with:
        packages: 'tools platform-tools platforms;android-36 build-tools;36.0.0'
YAML
  if ( check_action "${tmp}" ) 2>/dev/null; then
    echo "::error::[android-sdk --self-test] le retour du paquet « tools » n'est pas détecté." >&2
    return 1
  fi

  # mutation 2 : paquet inconnu
  cat > "${tmp}/${ACTION_PATH}" <<'YAML'
runs:
  using: "composite"
  steps:
    - name: Setup Android SDK (platform 36)
      uses: android-actions/setup-android@40fd30fb8d7440372e1316f5d1809ec01dcd3699 # v4.0.1
      with:
        packages: 'platform-tools platforms;android-37-preview'
YAML
  if ( check_action "${tmp}" ) 2>/dev/null; then
    echo "::error::[android-sdk --self-test] un paquet inconnu n'est pas détecté." >&2
    return 1
  fi

  echo "ANDROID_SDK_PACKAGES_GUARD_SELF_TEST_OK"
}

if [[ "${1:-}" == "--self-test" ]]; then
  self_test
else
  check_action "${1:-$(git rev-parse --show-toplevel 2>/dev/null || echo '.')}"
  if [[ ${errors} -gt 0 ]]; then
    echo "::error::[android-sdk] ${errors} problème(s) — voir ci-dessus (#7519)."
    exit 1
  fi
  echo "✅  Paquets Android SDK connus de cmdline-tools 20.0 (platform 36 présent, aucun paquet hérité)."
fi
