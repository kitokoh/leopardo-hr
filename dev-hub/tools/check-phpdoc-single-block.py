#!/usr/bin/env python3
"""Garde « un seul docblock par déclaration » — issue #7582.

## Le défaut

En PHP, **seul le dernier docblock précédant une déclaration compte** : c'est lui
que la réflexion renvoie, et donc celui que PHPStan lit. Un fichier qui porte
deux docblocks successifs voit les annotations du premier **désarmées**,
silencieusement.

Constat d'origine (`EmployeeFactory`) : `/** @extends Factory<Employee> */` suivi
d'un long commentaire descriptif. L'`@extends` étant ignoré,
`Employee::factory()->create()` était typé `Illuminate\\Database\\Eloquent\\Model`
et **≈20 erreurs** du check requis « PHPStan — Strict » apparaissaient dans les
tests. Défaut invisible en lecture rapide, sans effet à l'exécution : il ne se
manifeste que plus tard, ailleurs, en erreurs de type.

## Ce que la garde distingue (et pourquoi c'est le cœur du sujet)

Toutes les occurrences ne se valent pas. La garde **bloque** sur la seule classe
nuisible :

- **DÉSARMÉ** — une annotation présente dans le premier docblock et **absente du
  docblock actif** : cette annotation n'est lue par personne. `bloque`.
- **REDONDANT** — annotations dupliquées dans le docblock actif (prose ou
  annotation recopiée) : rien n'est perdu, c'est cosmétique. `rapporte`, jamais
  bloquant.

Cette distinction a été mesurée sur le dépôt : **6 désarmés, 31 redondants**. Un
garde qui traite les 37 pareil serait soit bruyant, soit inutile.

## Le cas qui a justifié la séparation : `@param` orphelin ≠ `@param` dormant

Fusionner mécaniquement les deux docblocks des 6 cas a fait apparaître **2 vraies
erreurs** PHPStan (`@param references unknown parameter: $allowed / $slabs`) :
ces `@param` ne décrivaient pas la déclaration qu'ils précédaient. La bonne
correction n'était donc pas de les rattacher, mais de les rendre à leur
propriétaire — et dans un cas (`AbstractCountryRules`), le docblock mélangeait
la prose de **deux** méthodes (#1814 → `withTaxSlabs`, #1815 → `withCapsEnabled`).
**Une annotation désarmée peut cacher une annotation déplacée** : c'est un
défaut de documentation, pas seulement de format.

## Usage

    python3 dev-hub/tools/check-phpdoc-single-block.py            # le dépôt
    python3 dev-hub/tools/check-phpdoc-single-block.py --list     # inventaire détaillé
    python3 dev-hub/tools/check-phpdoc-single-block.py --self-test
"""

from __future__ import annotations

import re
import sys
import tempfile
from pathlib import Path

ANNOTATION = re.compile(r"@[\w-]+")
SCOPE = "api"


def docblocks(src: str) -> list[tuple[int, int]]:
    """Positions (début, fin exclusive) de chaque `/** … */`."""
    out: list[tuple[int, int]] = []
    i = 0
    while True:
        i = src.find("/**", i)
        if i < 0:
            break
        j = src.find("*/", i + 3)
        if j < 0:
            break
        out.append((i, j + 2))
        i = j + 2
    return out


def analyse(src: str) -> list[tuple[int, str, list[str]]]:
    """(ligne, classe, annotations concernées) pour chaque docblock suivi d'un autre.

    `classe` vaut `disarmed` (annotation lue par personne) ou `redundant`.
    """
    findings: list[tuple[int, str, list[str]]] = []
    blocks = docblocks(src)

    for k in range(len(blocks) - 1):
        (s1, e1), (s2, _e2) = blocks[k], blocks[k + 1]

        between = src[e1:s2]
        if between.strip() != "" or between.count("\n") > 1:
            continue  # du code sépare les deux docblocks : pas le motif

        first = set(ANNOTATION.findall(src[s1:e1]))
        if not first:
            continue  # premier docblock sans annotation : rien n'est désarmé

        lost = sorted(first - set(ANNOTATION.findall(src[s2:_e2])))
        line = src[:s1].count("\n") + 1
        findings.append((line, "disarmed" if lost else "redundant", lost))
        break  # un constat par fichier suffit à agir

    return findings


def scan(root: Path) -> list[tuple[str, int, str, list[str]]]:
    out: list[tuple[str, int, str, list[str]]] = []
    scope = root / SCOPE

    for path in sorted(scope.rglob("*.php")):
        if "vendor" in path.parts or "node_modules" in path.parts:
            continue
        src = path.read_text(encoding="utf-8", errors="replace")
        for line, kind, lost in analyse(src):
            out.append((str(path.relative_to(root)), line, kind, lost))

    return out


def run_self_test() -> int:
    with tempfile.TemporaryDirectory() as tmp:
        root = Path(tmp) / SCOPE
        root.mkdir(parents=True)

        (root / "Desarme.php").write_text(
            "<?php\n"
            "/**\n * @extends Factory<Thing>\n */\n"
            "/**\n * Une description.\n */\n"
            "class Desarme extends Factory\n{\n}\n",
            encoding="utf-8",
        )
        (root / "Redondant.php").write_text(
            "<?php\n"
            "/**\n * Prose.\n *\n * @mixin Builder<static>\n */\n"
            "/**\n * @property int $id\n * @mixin Builder<static>\n */\n"
            "class Redondant\n{\n}\n",
            encoding="utf-8",
        )
        (root / "Sain.php").write_text(
            "<?php\n"
            "/**\n * Prose avec @extends dans le MÊME bloc.\n * @extends Factory<Thing>\n */\n"
            "class Sain extends Factory\n{\n}\n",
            encoding="utf-8",
        )
        (root / "Separe.php").write_text(
            "<?php\n"
            "/**\n * @var int\n */\n"
            "$x = 1;\n\n"
            "/**\n * Description.\n */\n"
            "class Separe\n{\n}\n",
            encoding="utf-8",
        )

        got = {p: (kind, lost) for p, _l, kind, lost in scan(Path(tmp))}

        checks = [
            ("Desarme.php classé `disarmed` (annotation lue par personne)",
             got.get(f"{SCOPE}/Desarme.php", ("", []))[0] == "disarmed"),
            ("Redondant.php classé `redundant` (l'annotation est aussi dans le bloc actif)",
             got.get(f"{SCOPE}/Redondant.php", ("", []))[0] == "redundant"),
            ("Sain.php (un seul docblock) non signalé", f"{SCOPE}/Sain.php" not in got),
            ("Separe.php (code entre les docblocks) non signalé", f"{SCOPE}/Separe.php" not in got),
        ]

        failures = 0
        for name, ok in checks:
            failures += 0 if ok else 1
            print(f"{'  ok  ' if ok else ' FAIL '} {name}")

        print(f"\nSelf-test: {len(checks) - failures}/{len(checks)} contrôles conformes.")
        return 0 if failures == 0 else 1


def main(argv: list[str]) -> int:
    if "--self-test" in argv:
        return run_self_test()

    root = Path.cwd()
    try:
        findings = scan(root)
    except OSError as exc:
        print(f"::error::check-phpdoc-single-block : {exc}", file=sys.stderr)
        return 1

    disarmed = [f for f in findings if f[2] == "disarmed"]
    redundant = [f for f in findings if f[2] == "redundant"]

    if "--list" in argv:
        print(f"{len(findings)} occurrence(s) : {len(disarmed)} désarmée(s), {len(redundant)} redondante(s)\n")
        for path, line, kind, lost in findings:
            detail = f" — perdu : {', '.join(lost)}" if lost else " — rien de perdu"
            print(f"  [{'DÉSARMÉ' if kind == 'disarmed' else 'redondant'}] {path}:{line}{detail}")
        return 0

    if not disarmed:
        print(
            f"check-phpdoc-single-block : OK — 0 docblock désarmé "
            f"({len(redundant)} redondant(s), non bloquant)."
        )
        return 0

    print(
        "check-phpdoc-single-block : docblock(s) DÉSARMÉ(S) — en PHP seul le dernier "
        "docblock compte, les annotations du premier ne sont lues par personne (issue #7582) :\n",
        file=sys.stderr,
    )
    for path, line, _kind, lost in disarmed:
        print(f"  - {path}:{line} — annotation(s) ignorée(s) : {', '.join(lost)}", file=sys.stderr)
    print(
        "\nRemède : un seul docblock par déclaration (description ET annotations dans le "
        "même bloc). Attention : si PHPStan répond « @param references unknown parameter », "
        "l'annotation n'est pas dormante mais DÉPLACÉE — la rendre à sa méthode, pas la "
        "rattacher à celle qu'elle précède.",
        file=sys.stderr,
    )
    return 1


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))
