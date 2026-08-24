#!/usr/bin/env python3
"""
Issue #5257 — garde CI i18n du module Payroll (zéro chaîne hardcodée utilisateur).

Trois vérifications :
  1. Aucun littéral `'message' => '...'` non localisé dans le module Payroll
     (les messages API utilisateur doivent passer par `__('payroll.*')`).
  2. Parité des clés `api/lang/{fr,en,tr,ar}/payroll.php` (mêmes clés ×4).
  3. Les libellés connus de `PayrollLineLabels::MAP` existent dans les 4
     catalogues (sinon le rendu retombe silencieusement sur le libellé brut).

Usage : python3 dev-hub/tools/check-payroll-i18n.py
Exit 0 = vert ; exit 1 = rouge (chaque problème est listé).
"""
from __future__ import annotations

import pathlib
import re
import sys

REPO = pathlib.Path(__file__).resolve().parents[2]
PAYROLL = REPO / "api" / "app" / "Modules" / "Payroll"
LANGS = ["fr", "en", "tr", "ar"]

errors: list[str] = []

# 1. Littéraux `message => '...'` non localisés
message_literal = re.compile(r"['\"]message['\"]\s*=>\s*['\"]([^'\"]{4,})['\"]")
for php in sorted(PAYROLL.rglob("*.php")):
    for i, line in enumerate(php.read_text(encoding="utf-8").splitlines(), 1):
        m = message_literal.search(line)
        if not m:
            continue
        # Faux positifs : clés de catalogue (payroll.xxx / errors.xxx), codes
        # machine en MAJUSCULES (contrats API, ex. PAYROLL_RUN_VALIDATION_FAILED),
        # appels __() déjà localisés, commentaires.
        text = m.group(1)
        if "__(" in line or text.startswith(("payroll.", "errors.", "pdf.")):
            continue
        if text.isupper() and "_" in text:
            continue
        errors.append(f"{php.relative_to(REPO)}:{i}  littéral message non localisé : {text!r}")

# 2. Parité des clés ×4
def catalog_keys(path: pathlib.Path) -> set[str]:
    return set(re.findall(r"^\s*'([a-z0-9_]+)'\s*=>", path.read_text(encoding="utf-8"), re.M))

catalogs = {loc: catalog_keys(REPO / "api" / "lang" / loc / "payroll.php") for loc in LANGS}
base = set().union(*catalogs.values())
for loc, keys in catalogs.items():
    missing = base - keys
    if missing:
        errors.append(f"payroll.php ({loc}) : clés manquantes vs les autres locales : {sorted(missing)}")

# 3. Libellés du moteur → clés présentes dans les 4 catalogues
helper = (PAYROLL / "Infrastructure" / "Services" / "PayrollLineLabels.php").read_text(encoding="utf-8")
map_keys = set(re.findall(r"=>\s*'(line_[a-z_]+)'", helper))
for key in sorted(map_keys):
    for loc in LANGS:
        if key not in catalogs[loc]:
            errors.append(f"PayrollLineLabels::{key} absent de payroll.php ({loc})")

if errors:
    print("PAYROLL_I18N_FAIL")
    for e in errors:
        print(f"  - {e}")
    sys.exit(1)

print("PAYROLL_I18N_OK — 0 chaîne hardcodée utilisateur, parité ×4")
