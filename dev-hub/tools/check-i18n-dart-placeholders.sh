#!/usr/bin/env bash
# ============================================================
# check-i18n-dart-placeholders.sh — Garde i18n : placeholder -> identifiant Dart (issue #9903)
#
# POURQUOI CETTE GARDE EXISTE
#   `flutter gen-l10n` derive le NOM DE CHAQUE PARAMETRE du nom du placeholder ICU.
#   Un catalogue contenant `{class}` produit donc :
#
#       String eduFeechargesBatchclassbanner(Object charges, Object class, Object students)
#
#   `class` est un mot reserve Dart : le fichier genere ne se parse pas, `dart format`
#   sort en exit 65 et le job echoue. Le message d'erreur ne nomme JAMAIS la cle
#   fautive — une seule cle a fait tomber 23 jobs le 2026-11-24 (#9901).
#
# CE QUE LES AUTRES GARDES NE VOIENT PAS
#   check-i18n-catalog-parity.sh verifie la PARITE des cles entre les 4 langues ;
#   validate.js / sync-mobile.js / sync-web.js / sync-backend.js et le
#   `git diff --exit-code` de `validate-and-sync` passent tous : la cle est presente
#   partout, synchronisee, et le JSON est valide. Le defaut n'existe qu'au moment ou
#   Dart NOMME un parametre — c'est-a-dire jamais avant `flutter gen-l10n`.
#
# CE QUI EST VERIFIE
#   Tout placeholder de `shared/i18n/locales/*.json` (source recopiee dans les ARB par
#   sync-mobile.js) et de `front/mobile_apps/*/lib/l10n/*.arb` doit produire un
#   identifiant Dart valide :
#     - mot reserve Dart (class, if, new, switch, with, ...)
#     - premier caractere non-identifiant (ex. `{2fa}`)
#     - caractere interdit dans un identifiant (ex. `{user-name}`, `{user.name}`)
#
# Usage:
#   bash dev-hub/tools/check-i18n-dart-placeholders.sh [root_dir]   # garde
#   bash dev-hub/tools/check-i18n-dart-placeholders.sh --self-test  # auto-test
# Exit 0 = aucun placeholder invalide ; exit 1 = au moins un (messages ::error::).
# ============================================================
set -uo pipefail

if [[ "${1:-}" == "--self-test" ]]; then
  set -e
  SELF="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/$(basename "${BASH_SOURCE[0]}")"
  TMP="$(mktemp -d)"
  trap 'rm -rf "$TMP"' EXIT
  fails=0

  # cas <nom> <attendu 0|1> <fichier relatif> <contenu json>
  case_fixture() {
    local name="$1" expected="$2" rel="$3" content="$4"
    local root="$TMP/$name"
    mkdir -p "$root/$(dirname "$rel")"
    printf '%s\n' "$content" > "$root/$rel"
    local status=0
    bash "$SELF" "$root" >/dev/null 2>&1 || status=$?
    if [[ "$status" -ne "$expected" ]]; then
      echo "::error::self-test '$name' : exit $status attendu $expected"
      fails=$((fails + 1))
    fi
  }

  # 1. Le cas reel du 2026-11-24 : `{class}` dans un catalogue partage.
  case_fixture reserved_shared 1 "shared/i18n/locales/fr.json" \
    '{"edu.feeCharges.batchClassBanner": "Repartition des {charges} {class} {students}"}'
  # 2. Contre-epreuve : le nom corrige passe.
  case_fixture renamed_ok 0 "shared/i18n/locales/fr.json" \
    '{"edu.feeCharges.batchClassBanner": "Repartition des {charges} {className} {students}"}'
  # 3. Premier caractere non-identifiant.
  case_fixture leading_digit 1 "front/mobile_apps/leopardo_core/lib/l10n/app_fr.arb" \
    '{"authSecondFactor": "Code {2fa} requis"}'
  # 4. Caractere interdit dans un identifiant.
  case_fixture bad_char 1 "front/mobile_apps/leopardo_core/lib/l10n/app_fr.arb" \
    '{"userFullName": "Bonjour {user-name}"}'
  # 5. Le mot reserve n'apparait QUE dans un ARB (pas dans shared) : la garde doit voir les deux.
  case_fixture reserved_arb 1 "front/mobile_apps/leopardo_core/lib/l10n/app_en.arb" \
    '{"groupLabel": "Group of {class} students"}'
  # 6. Pluriel ICU : les options (`=0`, `one`, `other`) ne sont pas des placeholders.
  case_fixture icu_plural_ok 0 "front/mobile_apps/leopardo_core/lib/l10n/app_fr.arb" \
    '{"itemsCount": "{count, plural, =0{aucun article} one{un article} other{{count} articles}}"}'
  # 7. Un mot reserve cache dans une option de pluriel est vu.
  case_fixture reserved_in_plural 1 "front/mobile_apps/leopardo_core/lib/l10n/app_fr.arb" \
    '{"itemsCount": "{count, plural, =0{aucun {class}} other{{count} articles}}"}'

  if [[ "$fails" -gt 0 ]]; then
    echo "check-i18n-dart-placeholders : self-test ECHOUE ($fails cas)."
    exit 1
  fi
  echo "check-i18n-dart-placeholders : self-test OK (7 cas — 4 rouges, 3 verts)."
  exit 0
fi

ROOT_DIR="${1:-$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)}"

PYTHONIOENCODING=utf-8 python3 - "$ROOT_DIR" <<'PYEOF'
import glob
import json
import os
import re
import sys

root = sys.argv[1]

# Mots reserves Dart (table « Reserved Words » du langage) : un parametre qui porte
# l'un de ces noms ne se parse pas. Les identifiants integres (`await`, `yield`,
# `dynamic`, `Function`, `get`, `set`, ...) restent utilisables comme parametres et
# ne sont donc PAS listes — les inclure produirait des faux rouges.
DART_RESERVED = {
    "assert", "break", "case", "catch", "class", "const", "continue", "default",
    "do", "else", "enum", "extends", "false", "final", "finally", "for", "if",
    "in", "is", "new", "null", "rethrow", "return", "super", "switch", "this",
    "throw", "true", "try", "var", "void", "while", "with",
}

DART_IDENTIFIER = re.compile(r"^[A-Za-z_$][A-Za-z0-9_$]*$")


def placeholders(text):
    """Noms de placeholders ICU d'une chaine (profondeur non interpretee : on lit
    l'identifiant apres chaque `{`, ce qui couvre les imbrications de pluriel)."""
    names = []
    i, n = 0, len(text)
    while i < n:
        if text[i] != "{":
            i += 1
            continue
        j = i + 1
        while j < n and text[j] not in ",}{":
            j += 1
        token = text[i + 1:j].strip()
        if token and " " not in token:
            names.append(token)
        i += 1
    return names


def reason(name):
    if not DART_IDENTIFIER.match(name):
        return "n'est pas un identifiant Dart valide"
    if name in DART_RESERVED:
        return "est un mot reserve Dart"
    return None


errors = []
checked_files = 0
checked_placeholders = 0

sources = sorted(glob.glob(os.path.join(root, "shared/i18n/locales/*.json")))
sources += sorted(glob.glob(os.path.join(root, "front/mobile_apps/*/lib/l10n/*.arb")))

for path in sources:
    rel = os.path.relpath(path, root).replace(os.sep, "/")
    try:
        with open(path, encoding="utf-8") as f:
            data = json.load(f)
    except Exception as exc:  # noqa: BLE001 — garde legere, le JSON est valide ailleurs
        errors.append(f"{rel} : illisible ({exc})")
        continue
    checked_files += 1
    for key, value in data.items():
        if key.startswith("@"):  # metadonnees ARB
            continue
        if not isinstance(value, str):
            continue
        for name in placeholders(value):
            checked_placeholders += 1
            why = reason(name)
            if why:
                errors.append(
                    f"{rel} : clé '{key}' — placeholder '{{{name}}}' {why} ; "
                    f"`flutter gen-l10n` génère un paramètre Dart invalide (dart format exit 65)."
                )

if errors:
    for e in errors:
        print(f"::error::check-i18n-dart-placeholders : {e}")
    print(
        f"::error::check-i18n-dart-placeholders : {len(errors)} placeholder(s) incompatible(s) "
        "avec la génération Dart — renommer le placeholder (ex. `{class}` -> `{className}`) "
        "dans les 4 catalogues partagés puis rejouer les 3 syncs (cf. #9901, #9903)."
    )
    sys.exit(1)

print(
    "check-i18n-dart-placeholders : OK — "
    f"{checked_placeholders} placeholder(s) sur {checked_files} catalogue(s) produisent "
    "un identifiant Dart valide."
)
PYEOF
