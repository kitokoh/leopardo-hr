#!/usr/bin/env bash
# Garde CI — désynchronisation ARB <-> localizations générées (récurrence #4762).
#
# Vérifie que TOUTES les clés des ARB de leopardo_core (fr/en/ar/tr) sont
# déclarées dans generated/app_localizations.dart. Une clé ARB absente des
# générés = erreur de compile dans les apps qui la référencent (ex. #4762 :
# 13 clés userAuth* ajoutées aux ARB sans `flutter gen-l10n`).
#
# Usage: bash dev-hub/tools/check-mobile-l10n-sync.sh
# Exit 0 = synchro OK ; exit 1 = clés ARB manquantes dans les générés.
set -u

L10N="front/mobile_apps/leopardo_core/lib/l10n"
GEN="$L10N/generated/app_localizations.dart"

if [[ ! -f "$GEN" || ! -f "$L10N/app_fr.arb" ]]; then
  echo "::error::check-mobile-l10n-sync : fichiers introuvables ($L10N). Lancer depuis la racine du repo."
  exit 1
fi

python3 - "$L10N" "$GEN" <<'PYEOF'
import json, re, sys

l10n, gen_path = sys.argv[1], sys.argv[2]

def arb_keys(path):
    with open(path, encoding="utf-8") as f:
        data = json.load(f)
    return {k for k in data if not k.startswith("@")}

with open(gen_path, encoding="utf-8") as f:
    src = f.read()
existing = set()
for m in re.finditer(r"^  (?:String|int|double|DateTime|bool|Object|List<String>) get (\w+);", src, re.M):
    existing.add(m.group(1))
for m in re.finditer(r"^  (?:String|int|double|DateTime|bool|Object|List<String>) (\w+)\(", src, re.M):
    existing.add(m.group(1))

missing_total = 0
for loc in ["fr", "en", "ar", "tr"]:
    path = f"{l10n}/app_{loc}.arb"
    keys = arb_keys(path)
    missing = sorted(keys - existing)
    for k in missing:
        print(f"::error::Clé ARB '{k}' (app_{loc}.arb) absente de {gen_path} — lancer 'flutter gen-l10n' dans leopardo_core.")
    missing_total += len(missing)

if missing_total:
    print(f"::error::check-mobile-l10n-sync : {missing_total} clé(s) ARB manquante(s) dans les localizations générées (cf. #4762).")
    sys.exit(1)

print("check-mobile-l10n-sync : OK — toutes les clés ARB (fr/en/ar/tr) sont présentes dans les générés.")
PYEOF
