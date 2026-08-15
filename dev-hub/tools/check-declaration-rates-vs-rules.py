#!/usr/bin/env python3
"""
check-declaration-rates-vs-rules.py — Issue #2539.

Garde anti-dérive : les générateurs de déclaration CSV (IPRES/CSS SN,
CNSS CI, CNPS GA/CG…) ne doivent PAS dupliquer les taux de cotisation du
moteur de paie. Un taux modifié dans les règles pays (socialContributions)
mais oublié dans un générateur produit une déclaration fausse sans rouge CI.

Ce script compare statiquement (regex, sans exécuter PHP) les constantes
`RATE_*`/`*_CAP` des générateurs avec les valeurs `rate`/`cap` déclarées dans
les règles pays correspondantes.

Usage :
  python3 dev-hub/tools/check-declaration-rates-vs-rules.py
Sortie : code 0 si cohérent, 1 sinon (liste les divergences).

Table des paires surveillées (étendre au fil des générateurs migrés) :
  (fichier générateur, constante, fichier règles, code contribution, champ)
"""

from __future__ import annotations

import re
import sys
from pathlib import Path

REPO_ROOT = Path(__file__).resolve().parents[2]
API = REPO_ROOT / "api"

# (generator_path, constant, rules_path, contribution_code, field)
PAIRS: list[tuple[str, str, str, str, str]] = [
    (
        "app/Modules/Payroll/Infrastructure/Services/IpresDeclarationGenerator.php",
        "RATE_CSS_FAMILLE_PAT",
        "app/Modules/Payroll/Infrastructure/Services/CountryRules/SenegalPayrollRules.php",
        "CSS_SN_PAT_FAM",
        "rate",
    ),
    (
        "app/Modules/Payroll/Infrastructure/Services/IpresDeclarationGenerator.php",
        "CSS_FAMILLE_CAP",
        "app/Modules/Payroll/Infrastructure/Services/CountryRules/SenegalPayrollRules.php",
        "CSS_SN_PAT_FAM",
        "cap",
    ),
]


def read(path: str) -> str:
    full = API / path
    if not full.exists():
        print(f"SKIP: {path} introuvable (retiré ?)")
        return ""
    return full.read_text(encoding="utf-8")


def const_value(src: str, name: str) -> float | None:
    m = re.search(rf"const\s+{re.escape(name)}\s*=\s*([0-9]+(?:\.[0-9]+)?)", src)
    return float(m.group(1)) if m else None


def rules_value(src: str, code: str, field: str) -> float | None:
    # Cherche le bloc de la contribution : 'code' => 'CSS_SN_PAT_FAM', ...
    # jusqu'à la fin de l'élément de tableau (ligne suivante commençant par ']' ou '[').
    idx = src.find(f"'code' => '{code}'")
    if idx == -1:
        return None
    # Rembobine au début de l'élément (ligne précédente '[').
    start = src.rfind("[", 0, idx)
    if start == -1:
        start = idx
    end = src.find("]", idx)
    block = src[start : (end + 1) if end != -1 else idx + 200]
    m = re.search(rf"'{re.escape(field)}'\s*=>\s*([0-9]+(?:\.[0-9]+)?)", block)
    return float(m.group(1)) if m else None


def main() -> int:
    errors = 0
    for gen_path, const, rules_path, code, field in PAIRS:
        gen = read(gen_path)
        rules = read(rules_path)
        gen_v = const_value(gen, const)
        rules_v = rules_value(rules, code, field)
        if gen_v is None:
            print(f"SKIP: constante {const} introuvable dans {gen_path}")
            continue
        if rules_v is None:
            print(f"SKIP: contribution {code}.{field} introuvable dans {rules_path}")
            continue
        if gen_v != rules_v:
            print(f"DIVERGENCE ({code}.{field}) : {gen_path}::{const} = {gen_v} "
                  f"vs {rules_path} = {rules_v} — le générateur doit lire le moteur (#2539)")
            errors += 1
        else:
            print(f"OK: {const} = {gen_v} == {code}.{field} ({rules_v})")

    if errors:
        print(f"\n❌ {errors} divergence(s) — corriger les générateurs (issue #2539).")
        return 1
    print("\n✅ Déclarations et moteur cohérents.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
