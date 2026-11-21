#!/usr/bin/env python3
"""Issue #9707 — garde CI « les `<file>` de phpunit.xml designent un vrai fichier ».

## Pourquoi cette garde existe

`api/phpunit.xml` listait 60 entrees `<file>` avec un **antislash** au lieu d'un
separateur de repertoire :

    <file>tests/Feature/Payroll/Golden\\GoldenBfPayrollTest.php</file>

PHPUnit traite l'antislash comme un caractere de nom de fichier, pas comme un
separateur. Mesure sur `main` :

    $ vendor/bin/phpunit --testsuite=Core --list-tests
    Test file ".../tests/Feature/Core/Auth\\PiiHmacLookupServiceTest.php" not found

Ces 60 fichiers n'etaient donc **jamais charges** par les testsuites qui les
listent : absents de la couverture, absents de toute execution ciblee par shard.
Le defaut est silencieux — PHPUnit sort en 0 et affiche simplement « not found ».

Cause racine : `api/dev-tools/generate-feature-shards.php` construisait le chemin
avec `substr($entry->getPathname(), ...)`, qui conserve le separateur natif de la
plateforme. Le fichier a ete regenere sous Windows puis commite tel quel.

## Ce qu'elle refuse

Toute entree `<file>` de `api/phpunit.xml` qui :

  1. contient un antislash (chemin non portable) ;
  2. ne correspond a **aucun fichier reel** sur disque (chemin mort) ;
  3. est un chemin absolu (doit etre relatif a `api/`).

Le point 2 est le plus important : il attrape aussi les fichiers renommes ou
supprimes sans regeneration des shards, qui produisent le meme « not found »
silencieux.

## Usage

    python3 dev-hub/tools/check-phpunit-file-paths.py
    python3 dev-hub/tools/check-phpunit-file-paths.py --verbose
    python3 dev-hub/tools/check-phpunit-file-paths.py --self-test

Code de sortie 0 = conforme, 1 = violation, 2 = erreur d'usage.
"""

from __future__ import annotations

import argparse
import re
import sys
import tempfile
from pathlib import Path

REPO_ROOT = Path(__file__).resolve().parents[2]
DEFAULT_PHPUNIT = REPO_ROOT / "api" / "phpunit.xml"
API_ROOT = REPO_ROOT / "api"

FILE_ENTRY = re.compile(r"<file>\s*([^<]+?)\s*</file>")


def analyse(phpunit_path: Path, api_root: Path) -> list[tuple[int, str]]:
    """Retourne [(ligne, message)] des entrees `<file>` invalides."""
    if not phpunit_path.is_file():
        raise FileNotFoundError(phpunit_path)

    text = phpunit_path.read_text(encoding="utf-8", errors="replace")
    violations: list[tuple[int, str]] = []

    for match in FILE_ENTRY.finditer(text):
        raw = match.group(1)
        line = text[: match.start()].count("\n") + 1

        if "\\" in raw:
            violations.append(
                (
                    line,
                    f"antislash dans le chemin « {raw} » : PHPUnit le traite comme "
                    f"un nom de fichier, pas un separateur, et ne charge jamais le "
                    f"test (utiliser « {raw.replace(chr(92), '/')} »)",
                )
            )
            continue

        if raw.startswith("/"):
            violations.append((line, f"chemin absolu « {raw} » : doit etre relatif a api/"))
            continue

        if not (api_root / raw).is_file():
            violations.append(
                (line, f"fichier introuvable « {raw} » : entree morte, le test n'est jamais charge")
            )

    return violations


# ---------------------------------------------------------------------------
# Autotest : prouve que la garde attrape les trois formes de defaut.
# ---------------------------------------------------------------------------

SELF_TEST_CASES: list[tuple[str, str, bool]] = [
    (
        "antislash (forme exacte du bug #9707)",
        """<phpunit><testsuites><testsuite name="X">
        <file>tests/Feature/Payroll/Golden\\GoldenBfPayrollTest.php</file>
        </testsuite></testsuites></phpunit>""",
        True,
    ),
    (
        "chemin mort (fichier renomme sans regeneration)",
        """<phpunit><testsuites><testsuite name="X">
        <file>tests/Feature/DoesNotExistTest.php</file>
        </testsuite></testsuites></phpunit>""",
        True,
    ),
    (
        "chemin absolu",
        """<phpunit><testsuites><testsuite name="X">
        <file>/tmp/SomeTest.php</file>
        </testsuite></testsuites></phpunit>""",
        True,
    ),
    (
        "chemin correct",
        """<phpunit><testsuites><testsuite name="X">
        <file>tests/Feature/ExampleTest.php</file>
        </testsuite></testsuites></phpunit>""",
        False,
    ),
]


def self_test() -> int:
    failures = 0
    with tempfile.TemporaryDirectory() as tmp:
        api_root = Path(tmp)
        (api_root / "tests" / "Feature").mkdir(parents=True)
        (api_root / "tests" / "Feature" / "ExampleTest.php").write_text("<?php\n")

        for name, xml, should_fail in SELF_TEST_CASES:
            phpunit = api_root / "phpunit.xml"
            phpunit.write_text(xml)
            found = analyse(phpunit, api_root)
            ok = bool(found) == should_fail
            status = "OK  " if ok else "FAIL"
            print(
                f"  [{status}] {name} -> {len(found)} violation(s), "
                f"attendu {'>=1' if should_fail else '0'}"
            )
            if not ok:
                failures += 1

    if failures:
        print(f"\nAUTOTEST ECHEC — {failures} cas.", file=sys.stderr)
        return 1
    print(f"\nAUTOTEST OK — {len(SELF_TEST_CASES)} cas.")
    return 0


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--verbose", action="store_true")
    parser.add_argument("--self-test", action="store_true")
    parser.add_argument("--phpunit", default=str(DEFAULT_PHPUNIT))
    parser.add_argument("--api-root", default=str(API_ROOT))
    args = parser.parse_args()

    if args.self_test:
        return self_test()

    phpunit_path = Path(args.phpunit)
    api_root = Path(args.api_root)

    try:
        violations = analyse(phpunit_path, api_root)
    except FileNotFoundError:
        print(f"ERREUR — fichier introuvable : {phpunit_path}", file=sys.stderr)
        return 2

    total = len(FILE_ENTRY.findall(phpunit_path.read_text(encoding="utf-8", errors="replace")))

    for line, message in violations:
        print(f"{phpunit_path.name}:{line}: {message}", file=sys.stderr)

    if args.verbose:
        print(f"Entrees <file> analysees : {total}")

    if violations:
        print("", file=sys.stderr)
        print(
            f"ECHEC — {len(violations)} entree(s) <file> invalide(s) sur {total}. "
            f"Regenerer : php api/dev-tools/generate-feature-shards.php",
            file=sys.stderr,
        )
        return 1

    print(f"OK — {total} entrees <file> resolvent un fichier reel.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
