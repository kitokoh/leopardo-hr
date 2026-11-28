#!/usr/bin/env bash
#
# #10038 — Garde de la doctrine « app non-Flutter » (Expo / React Native).
#
# ## Le defaut qu'elle attrape
#
# Le depot heberge 19 applications **Flutter** sous `front/mobile_apps/`, gardees
# par `check-mobile-layer-guard.sh` (registre fail-closed) et
# `check-mobile-app-identities.sh` (applicationId Android + bundle id iOS
# uniques, entrees dans deux registres et trois matrices CI).
#
# Deux applications **Expo / React Native** ont ete ajoutees sous
# `front/mobile_expo/`. Elles n'ont ni `android/app/build.gradle.kts`, ni
# `ios/Runner.xcodeproj`, ni `pubspec.yaml` : les gardes Flutter les traitent
# donc comme des apps incompletes — a raison, puisque la garde d'identites
# protege une propriete reelle (deux apps ne doivent jamais partager un
# identifiant). Mais la doctrine qui les sort de ce perimetre n'existait que
# sous forme de PROSE dans deux README : rien ne l'appliquait. Une app Expo
# ajoutee par erreur a `melos.yaml` ou a une matrice Flutter aurait casse la CI
# mobile sans qu'aucun garde ne nomme la cause.
#
# ## Ce que la garde verifie (doctrine, rendue executable)
#
#   D1  Chaque app Expo vit sous `front/mobile_expo/<nom>/` et porte un
#       `app.json` avec `expo.slug`, `expo.version`, `expo.android.package` et
#       `expo.ios.bundleIdentifier` — sans quoi elle n'est pas distribuable.
#   D2  Aucune app Expo n'est listee dans `melos.yaml` (melos ne gere que des
#       packages Dart ; y mettre une app Expo casse `melos bootstrap`).
#   D3  Aucune app Expo n'est listee dans les matrices Flutter
#       (`mobile-apps-ci.yml`, `mobile-distribute.yml`,
#       `mobile-distribute-main.yml`) : ces matrices compilent des APK via
#       `flutter build apk` et distribuent par Firebase App Distribution. Une
#       app Expo se distribue par EAS Build.
#   D4  Aucune app Expo n'introduit de dependance Flutter : pas de
#       `pubspec.yaml`, et `package.json` ne declare ni `flutter` ni
#       `dart` en dependance.
#   D5  Les identifiants (`android.package` / `ios.bundleIdentifier`) d'une app
#       Expo sont UNIQUES entre apps Expo ET ne collisionnent avec AUCUN
#       `applicationId` Flutter. C'est la propriete que protege
#       `check-mobile-app-identities.sh` cote Flutter ; sans D5, une app Expo
#       pouvait reutiliser `com.leopardo.accounting` sans que rien ne le voie.
#   D6  Chaque app Expo est documentee dans `front/mobile_apps/README.md`
#       (section « Applications presentes sous front/mobile_expo/ ») — une app
#       que personne ne sait lancer n'est pas livree.
#
# Usage : dev-hub/tools/check-mobile-expo-doctrine.sh [racine]
#   racine : defaut « . »
#
# Codes de sortie : 0 = doctrine respectee ; 1 = violation ; 2 = usage.

set -euo pipefail

ROOT="${1:-.}"
EXPO_DIR="$ROOT/front/mobile_expo"
FLUTTER_DIR="$ROOT/front/mobile_apps"
MELOS="$ROOT/melos.yaml"
README="$ROOT/front/mobile_apps/README.md"

if [[ ! -d "$EXPO_DIR" ]]; then
  echo "OK — garde doctrine Expo : aucune app Expo (front/mobile_expo/ absent)."
  exit 0
fi

python3 - "$ROOT" "$EXPO_DIR" "$FLUTTER_DIR" "$MELOS" "$README" <<'PYEOF'
import json
import os
import re
import sys

root, expo_dir, flutter_dir, melos_path, readme_path = sys.argv[1:6]

errors = []
apps = []

for name in sorted(os.listdir(expo_dir)):
    path = os.path.join(expo_dir, name)
    if not os.path.isdir(path):
        continue
    apps.append((name, path))

if not apps:
    print("OK — garde doctrine Expo : aucune app Expo.")
    sys.exit(0)

# --- D1 : app.json complet -------------------------------------------------
identities = {}   # (kind, value) -> app
for name, path in apps:
    app_json = os.path.join(path, "app.json")
    if not os.path.isfile(app_json):
        errors.append(f"D1 : « {name} » n'a pas de app.json — ce n'est pas une app Expo.")
        continue
    try:
        with open(app_json, encoding="utf-8") as fh:
            expo = (json.load(fh) or {}).get("expo") or {}
    except Exception as exc:  # noqa: BLE001
        errors.append(f"D1 : « {name} »/app.json illisible ({exc}).")
        continue

    for key in ("slug", "version"):
        if not expo.get(key):
            errors.append(f"D1 : « {name} » n'a pas `expo.{key}` dans app.json.")

    android_pkg = (expo.get("android") or {}).get("package")
    ios_bundle = (expo.get("ios") or {}).get("bundleIdentifier")
    if not android_pkg:
        errors.append(f"D1 : « {name} » n'a pas `expo.android.package` — APK non installable.")
    if not ios_bundle:
        errors.append(f"D1 : « {name} » n'a pas `expo.ios.bundleIdentifier` — build iOS impossible.")

    # --- D4 : pas de dependance Flutter ------------------------------------
    if os.path.isfile(os.path.join(path, "pubspec.yaml")):
        errors.append(
            f"D4 : « {name} » porte un pubspec.yaml — une app Expo n'est pas un package Dart."
        )
    pkg_json = os.path.join(path, "package.json")
    if os.path.isfile(pkg_json):
        try:
            with open(pkg_json, encoding="utf-8") as fh:
                pkg = json.load(fh) or {}
        except Exception:  # noqa: BLE001
            pkg = {}
        for section in ("dependencies", "devDependencies"):
            deps = pkg.get(section) or {}
            for forbidden in ("flutter", "dart"):
                if forbidden in deps:
                    errors.append(
                        f"D4 : « {name} »/package.json declare « {forbidden} » en {section} — "
                        "une app Expo n'introduit aucune dependance Flutter."
                    )

    # --- D5 : collecte des identites ---------------------------------------
    if android_pkg:
        identities.setdefault(("android", android_pkg), []).append(name)
    if ios_bundle:
        identities.setdefault(("ios", ios_bundle), []).append(name)

# --- D5 : unicite entre apps Expo ------------------------------------------
for (kind, value), owners in identities.items():
    if len(owners) > 1:
        errors.append(
            f"D5 : l'identifiant {kind} « {value} » est partage par "
            f"{', '.join(sorted(owners))} — deux apps ne peuvent pas porter le meme id."
        )

# --- D5 : collision avec les applicationId Flutter -------------------------
flutter_ids = {}
if os.path.isdir(flutter_dir):
    for name in sorted(os.listdir(flutter_dir)):
        gradle = os.path.join(flutter_dir, name, "android", "app", "build.gradle.kts")
        if not os.path.isfile(gradle):
            continue
        with open(gradle, encoding="utf-8") as fh:
            m = re.search(r'applicationId\s*=?\s*"([^"]+)"', fh.read())
        if m:
            flutter_ids[m.group(1)] = name

for (kind, value), owners in identities.items():
    if kind == "android" and value in flutter_ids:
        errors.append(
            f"D5 : l'app Expo « {owners[0]} » reutilise l'applicationId Flutter "
            f"« {value} » (deja porte par « {flutter_ids[value]} ») — les deux APK "
            "ne peuvent pas etre installes cote a cote."
        )

# --- D2 : melos.yaml -------------------------------------------------------
if os.path.isfile(melos_path):
    with open(melos_path, encoding="utf-8") as fh:
        melos = fh.read()
    for name, _ in apps:
        if re.search(rf"front/mobile_expo/{re.escape(name)}\b", melos):
            errors.append(
                f"D2 : « {name} » est listee dans melos.yaml — melos ne gere que des "
                "packages Dart, une app Expo n'y a pas sa place."
            )

# --- D3 : matrices Flutter -------------------------------------------------
matrices = [
    ".github/workflows/mobile-apps-ci.yml",
    ".github/workflows/mobile-distribute.yml",
    ".github/workflows/mobile-distribute-main.yml",
]
for rel in matrices:
    full = os.path.join(root, rel)
    if not os.path.isfile(full):
        continue
    with open(full, encoding="utf-8") as fh:
        content = fh.read()
    for name, _ in apps:
        if re.search(rf"front/mobile_expo/{re.escape(name)}\b", content):
            errors.append(
                f"D3 : « {name} » est listee dans {rel} — ces matrices compilent des APK "
                "Flutter via `flutter build apk` ; une app Expo se distribue par EAS Build."
            )

# --- D6 : documentation ----------------------------------------------------
if os.path.isfile(readme_path):
    with open(readme_path, encoding="utf-8") as fh:
        readme = fh.read()
    for name, _ in apps:
        if name not in readme:
            errors.append(
                f"D6 : « {name} » n'est pas documentee dans front/mobile_apps/README.md — "
                "une app que personne ne sait lancer n'est pas livree."
            )
else:
    errors.append("D6 : front/mobile_apps/README.md est absent — la doctrine n'est plus ecrite.")

if errors:
    print(f"❌ #10038 : doctrine « app non-Flutter » violee ({len(errors)} erreur(s)) :")
    for e in errors:
        print(f"   - {e}")
    print("   Doc : front/mobile_apps/README.md § Doctrine « app non-Flutter »")
    sys.exit(1)

print(
    f"✅ #10038 : doctrine « app non-Flutter » respectee "
    f"({len(apps)} app(s) Expo, {len(flutter_ids)} app(s) Flutter, identifiants uniques)."
)
PYEOF
