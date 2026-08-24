#!/usr/bin/env python3
"""
Issue #5227 — garde CI i18n du module Accounting (zéro chaîne hardcodée utilisateur).

Quatre vérifications (miroir de `check-payroll-i18n.py` #5257, étendu) :
  1. Aucun littéral `'message' => '...'` non localisé dans le module Accounting
     (les messages API utilisateur doivent passer par `__('accounting.*')`).
  2. Aucun littéral français brut dans `throw ...('...')` non localisé
     (hors codes machine MAJUSCULES et clés connues `accounting.*`/`errors.*`).
  3. Parité des clés `api/lang/{fr,en,tr,ar}/accounting.php` (mêmes clés ×4).
  4. Toutes les clés `__('accounting.*')` utilisées dans le module existent au
     catalogue (sinon la locale retombe sur la clé brute) ; idem les libellés
     PDF (`document_type_*` ×6, `status_*` ×6).
  5. Les codes `errors.*` des DomainException du module existent dans les 4
     catalogues `errors.php` (renderer #4171 : `PAYMENT_EXCEEDS_TOTAL`,
     `PAYMENT_ON_UNSENT_DOCUMENT`).

Usage : python3 dev-hub/tools/check-accounting-i18n.py
Exit 0 = vert ; exit 1 = rouge (chaque problème est listé).
"""
from __future__ import annotations

import pathlib
import re
import sys

REPO = pathlib.Path(__file__).resolve().parents[2]
ACCOUNTING = REPO / "api" / "app" / "Modules" / "Accounting"
LANGS = ["fr", "en", "tr", "ar"]

errors: list[str] = []

# 1. Littéraux `message => '...'` non localisés
message_literal = re.compile(r"['\"]message['\"]\s*=>\s*['\"]([^'\"]{4,})['\"]")
for php in sorted(ACCOUNTING.rglob("*.php")):
    for i, line in enumerate(php.read_text(encoding="utf-8").splitlines(), 1):
        m = message_literal.search(line)
        if not m:
            continue
        text = m.group(1)
        # Faux positifs : clés de catalogue (accounting.xxx / errors.xxx), codes
        # machine en MAJUSCULES (contrats API), appels __() déjà localisés.
        if "__(" in line or text.startswith(("accounting.", "errors.")):
            continue
        if text.isupper() and "_" in text:
            continue
        errors.append(f"{php.relative_to(REPO)}:{i}  littéral message non localisé : {text!r}")

# 2. Littéraux français dans `throw ...('...')` non localisés
throw_literal = re.compile(r"throw\s+(?:new\s+)?[A-Za-z_\\]+\((['\"])([^'\"]{4,})\1\)")
for php in sorted(ACCOUNTING.rglob("*.php")):
    for i, line in enumerate(php.read_text(encoding="utf-8").splitlines(), 1):
        m = throw_literal.search(line)
        if not m:
            continue
        text = m.group(2)
        if "__(" in line or text.startswith(("accounting.", "errors.", "pdf.")):
            continue
        if text.isupper() and "_" in text:
            continue
        errors.append(f"{php.relative_to(REPO)}:{i}  littéral throw non localisé : {text!r}")

# 3. Parité des clés ×4 (catalogues plats OU imbriqués — paths aplatis)
def catalog_keys(path: pathlib.Path) -> set[str]:
    """Retourne l'ensemble des chemins de clés (dotted) du catalogue PHP.

    Gère le format du module : clés racine plates (`'document_type_*'`,
    `'tva_label_*'`) et groupes imbriqués (`'errors' => [...]`,
    `'validation' => [...]` → paths `errors.wf_*`, `validation.amount_*`).
    """
    keys: set[str] = set()
    group: str | None = None
    for line in path.read_text(encoding="utf-8").splitlines():
        m = re.match(r"^(\s*)'([a-z0-9_]+)'\s*=>\s*(\[|['\"])", line)
        if not m:
            continue
        indent, key, opener = len(m.group(1)), m.group(2), m.group(3)
        if opener == '[' and indent == 4:
            group = key
        elif indent == 8 and group is not None:
            keys.add(f"{group}.{key}")
        elif indent == 4:
            keys.add(key)
    return keys

catalogs = {loc: catalog_keys(REPO / "api" / "lang" / loc / "accounting.php") for loc in LANGS}
base = set().union(*catalogs.values())
for loc, keys in catalogs.items():
    missing = base - keys
    if missing:
        errors.append(f"accounting.php ({loc}) : clés manquantes vs les autres locales : {sorted(missing)}")

# 4. Clés `__('accounting.*')` utilisées dans le module → présentes ×4
used_keys = set()
for php in ACCOUNTING.rglob("*.php"):
    used_keys |= set(re.findall(r"__\('accounting\.([a-z0-9_.]+)'", php.read_text(encoding="utf-8")))
# Les clés dynamiques (concaténation de suffixe au runtime, ex.
# `__('accounting.document_type_'.$document->type)`) se terminent par `_` :
# leur couverture est assurée par le check 4b (tous les suffixes énumérés).
for key in sorted(k for k in used_keys if not k.endswith("_")):
    for loc in LANGS:
        if key not in catalogs[loc]:
            errors.append(f"__('accounting.{key}') utilisé dans le module mais absent de accounting.php ({loc})")

# 4b. Libellés PDF : 6 types de document + 6 statuts présents ×4
for suffix in ["invoice", "proforma", "quote", "credit_note", "delivery_note", "receipt"]:
    key = f"document_type_{suffix}"
    for loc in LANGS:
        if key not in catalogs[loc]:
            errors.append(f"{key} absent de accounting.php ({loc}) — rendu PDF retomberait sur la clé brute")
for suffix in ["draft", "sent", "partially_paid", "paid", "cancelled", "overdue"]:
    key = f"status_{suffix}"
    for loc in LANGS:
        if key not in catalogs[loc]:
            errors.append(f"{key} absent de accounting.php ({loc}) — rendu PDF retomberait sur la clé brute")

# 5. Codes errors.* des DomainException du module → présents dans errors.php ×4
def error_codes(path: pathlib.Path) -> set[str]:
    return set(re.findall(r"^\s*'([A-Z][A-Z0-9_]+)'\s*=>", path.read_text(encoding="utf-8"), re.M))

error_catalogs = {loc: error_codes(REPO / "api" / "lang" / loc / "errors.php") for loc in LANGS}
required_codes = set()
for php in ACCOUNTING.rglob("*.php"):
    required_codes |= set(re.findall(r"errorCode\(\)|,\s*'([A-Z][A-Z0-9_]{5,})'\s*\)", php.read_text(encoding="utf-8")))
# Codes explicitement portés par les DomainException du module (constructeurs).
for php in (ACCOUNTING / "Domain" / "Exceptions").glob("*.php"):
    required_codes |= set(re.findall(r"'([A-Z][A-Z0-9_]{5,})'", php.read_text(encoding="utf-8")))
for code in sorted(required_codes):
    for loc in LANGS:
        if code not in error_catalogs[loc]:
            errors.append(f"errors.{code} absent de errors.php ({loc}) — renderer #4171 renverrait SERVER_ERROR")

if errors:
    print("ACCOUNTING_I18N_FAIL")
    for e in errors:
        print(f"  - {e}")
    sys.exit(1)

print("ACCOUNTING_I18N_OK — 0 chaîne hardcodée utilisateur, parité ×4")
