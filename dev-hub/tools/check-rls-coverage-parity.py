#!/usr/bin/env python3
"""Garde CI — la sonde RLS et les migrations doivent décrire les MÊMES tables.

## Le défaut que cette garde ferme

`php artisan tenant:rls-status` (ADR-9879, #9896) est la seule chose qui rend
l'état de RLS **mesurable**. Il compare la réalité PostgreSQL à une liste
`EXPECTED_TABLES` écrite en dur dans la commande.

Cette liste est une **seconde source de vérité** : les migrations disent quelles
tables portent `ENABLE`/`FORCE ROW LEVEL SECURITY` + une politique, la commande
dit lesquelles doivent être vérifiées. Rien ne les obligeait à rester d'accord.

C'est exactement la classe de défaut de #9896 : la migration #9879 avait tourné
en batch 2 **avant** la création de `bulk_payment_claims` en batch 3, son garde
`schemaTableExists()` l'a ignorée en silence, Laravel l'a marquée « Ran »
définitivement — et la table est restée avec `ENABLE`+`FORCE` mais **aucune
politique**. La sonde l'a révélé ; rien ne garantissait qu'elle continuerait.

Deux dérives possibles, toutes deux silencieuses :

  1. une migration couvre une nouvelle table, la commande ne la vérifie pas
     → la table est annoncée protégée sans que personne ne mesure ;
  2. la commande attend une table qu'aucune migration ne couvre
     → la sonde sort en code 2 pour toujours, et un rouge permanent ne
       signale plus rien (leçon #3545).

## Ce que la garde vérifie

  - chaque table de `EXPECTED_TABLES` est couverte par au moins une migration
    (`ENABLE ROW LEVEL SECURITY` ou `enableRls(`) ;
  - chaque table couverte par une migration figure dans `EXPECTED_TABLES` ;
  - les tables volontairement EXCLUES sont déclarées avec leur raison
    (`tax_slabs` : barèmes légaux partagés, `company_id` NULL).

## Usage

    python3 dev-hub/tools/check-rls-coverage-parity.py
    python3 dev-hub/tools/check-rls-coverage-parity.py --self-test
    python3 dev-hub/tools/check-rls-coverage-parity.py --verbose

Exit 0 = propre, 1 = dérive, 2 = erreur technique.
"""
from __future__ import annotations

import argparse
import pathlib
import re
import sys

ROOT = pathlib.Path(__file__).resolve().parents[2]
API = ROOT / "api"
COMMAND = API / "app/Console/Commands/TenantRlsStatusCommand.php"
MIGRATIONS = API / "database/migrations/tenant"

# Tables couvertes par une migration mais volontairement hors de la sonde.
# Toute entrée DOIT porter sa raison : une exclusion muette est une dette
# invisible, et c'est précisément ce que cette garde combat.
EXCLUDED_FROM_PROBE: dict[str, str] = {}

ENABLE_RE = re.compile(r"ALTER TABLE\s+([a-z_]+)\s+ENABLE ROW LEVEL SECURITY")
ENABLE_CALL_RE = re.compile(r"enableRls\(\s*'([a-z_]+)'")
# `private const TABLES = ['a', 'b'];` ou `'a' => true,` dans un tableau de lot.
TABLES_CONST_RE = re.compile(r"private const TABLES = \[(.*?)\];", re.S)
TABLES_MAP_RE = re.compile(r"private const TABLES = \[(.*?)\];", re.S)
QUOTED_RE = re.compile(r"'([a-z_]+)'")


def covered_by_migrations() -> set[str]:
    tables: set[str] = set()
    for path in sorted(MIGRATIONS.glob("*.php")):
        text = path.read_text(encoding="utf-8", errors="ignore")
        tables.update(ENABLE_RE.findall(text))
        tables.update(ENABLE_CALL_RE.findall(text))
        # Forme « lot » : private const TABLES = ['a' => true, 'b' => false];
        for block in TABLES_MAP_RE.findall(text):
            if "ENABLE ROW LEVEL SECURITY" in text or "enableRls(" in text:
                tables.update(QUOTED_RE.findall(block))
    return tables


def strip_php_comments(text: str) -> str:
    """Retire les commentaires en préservant les chaînes.

    Indispensable ici : le bloc `EXPECTED_TABLES` est abondamment commenté
    (« `tax_slabs` est volontairement ABSENTE : ses 6 lignes à `company_id`
    NULL… »). Sans ce nettoyage, `company_id` serait lu comme une table
    attendue et la garde signalerait une dérive qui n'existe pas.
    """
    out: list[str] = []
    i = 0
    n = len(text)
    quote: str | None = None
    while i < n:
        ch = text[i]
        if quote is not None:
            out.append(ch)
            if ch == "\\" and i + 1 < n:
                out.append(text[i + 1])
                i += 2
                continue
            if ch == quote:
                quote = None
            i += 1
            continue
        if ch in ("'", '"'):
            quote = ch
            out.append(ch)
            i += 1
            continue
        if ch == "/" and i + 1 < n and text[i + 1] == "/":
            while i < n and text[i] != "\n":
                i += 1
            continue
        if ch == "#":
            while i < n and text[i] != "\n":
                i += 1
            continue
        if ch == "/" and i + 1 < n and text[i + 1] == "*":
            i += 2
            while i + 1 < n and not (text[i] == "*" and text[i + 1] == "/"):
                i += 1
            i += 2
            continue
        out.append(ch)
        i += 1
    return "".join(out)


def expected_in_command() -> set[str]:
    text = strip_php_comments(COMMAND.read_text(encoding="utf-8", errors="ignore"))
    m = re.search(r"private const EXPECTED_TABLES = \[(.*?)\n    \];", text, re.S)
    if not m:
        raise RuntimeError("EXPECTED_TABLES introuvable dans TenantRlsStatusCommand.php")
    return set(QUOTED_RE.findall(m.group(1)))


def compare(expected: set[str], covered: set[str]) -> list[str]:
    problems: list[str] = []
    for table in sorted(expected - covered):
        problems.append(
            f"`{table}` est attendue par `tenant:rls-status` mais AUCUNE migration "
            f"ne pose ENABLE ROW LEVEL SECURITY dessus — la sonde sortira en code 2 "
            f"pour toujours (rouge permanent, leçon #3545)."
        )
    for table in sorted(covered - expected - set(EXCLUDED_FROM_PROBE)):
        problems.append(
            f"`{table}` est couverte par une migration RLS mais ABSENTE de "
            f"EXPECTED_TABLES — la table est annoncée protégée sans être mesurée. "
            f"Ajouter à la sonde, ou déclarer l'exclusion avec sa raison."
        )
    return problems


def check(verbose: bool = False) -> int:
    if not COMMAND.is_file():
        print(f"::error::Commande introuvable : {COMMAND}", file=sys.stderr)
        return 2
    if not MIGRATIONS.is_dir():
        print(f"::error::Migrations introuvables : {MIGRATIONS}", file=sys.stderr)
        return 2

    expected = expected_in_command()
    covered = covered_by_migrations()
    problems = compare(expected, covered)

    if verbose:
        print(f"  sonde     : {len(expected)} tables — {', '.join(sorted(expected))}")
        print(f"  migrations: {len(covered)} tables — {', '.join(sorted(covered))}")

    for p in problems:
        print(f"::error::{p}")

    if problems:
        print(f"\n❌ {len(problems)} dérive(s) entre la sonde RLS et les migrations.")
        return 1

    print(
        f"✅ Sonde RLS et migrations d'accord : {len(expected)} tables couvertes "
        f"et mesurées."
    )
    return 0


def self_test() -> int:
    failures = 0

    def expect(name: str, expected: set[str], covered: set[str], want: int) -> None:
        nonlocal failures
        got = len(compare(expected, covered))
        if got != want:
            failures += 1
            print(f"::error::self-test '{name}' : attendu {want}, obtenu {got}")

    expect("accord parfait", {"a", "b"}, {"a", "b"}, 0)
    expect("sonde attend une table non couverte", {"a", "b"}, {"a"}, 1)
    expect("migration couvre une table non sondée", {"a"}, {"a", "b"}, 1)
    expect("les deux dérives", {"a", "x"}, {"a", "y"}, 2)
    expect("ensembles vides", set(), set(), 0)

    if failures:
        print(f"❌ self-test : {failures} échec(s).")
        return 1
    print("✅ self-test : 5/5.")
    return 0


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--self-test", action="store_true")
    parser.add_argument("--verbose", action="store_true")
    args = parser.parse_args()
    if args.self_test:
        return self_test()
    return check(verbose=args.verbose)


if __name__ == "__main__":
    sys.exit(main())
