#!/usr/bin/env python3
"""Issue #9707 + #9802 — garde CI de la configuration PHPUnit.

## Deux controles, deux defauts distincts

### A. Sens « liste -> disque » (issue #9707)

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

Refuse : toute entree `<file>` qui
  1. contient un antislash (chemin non portable) ;
  2. ne correspond a **aucun fichier reel** sur disque (chemin mort) ;
  3. est un chemin absolu (doit etre relatif a `api/`).

### B. Sens « disque -> liste » (issue #9802)

Le controle A ne verifie qu'un sens. Il ne dit **jamais** si un fichier de test
existe sur disque sans etre execute par aucune suite. C'est le defaut le plus
couteux : un test qui ne tourne pas est indistinguable d'un test qui passe.

Mesure sur `main` (2026-11-22) : 1 327 fichiers `tests/Feature/**/*Test.php`,
`FeatureMvpFixture` en liste 235 et `FeatureRest` 1 081 — **11 fichiers hors de
toute suite**. Plus `tests/Arch/LayerRulesTest.php`, qui n'est dans aucune
testsuite du tout : les regles de couche PA2-ARCH-002 / ADR-0020 n'etaient donc
verifiees par aucune execution.

**Point cle** : la couverture est evaluee contre les testsuites que la CI
**execute reellement**, pas contre toutes les testsuites declarees. Sinon
`<testsuite name="Feature"><directory>tests/Feature</directory></testsuite>`
— declaree mais jamais lancee — rendrait tout « couvert » et la garde serait
verte a tort. La liste des suites executees est **derivee des workflows**
(`--testsuite=X` dans `.github/workflows/*.yml`), donc elle suit la CI au lieu
d'etre recopiee a la main.

Refuse : tout `api/tests/**/*Test.php` qu'aucune suite executee ne selectionne,
sauf allowlist explicite (fichier + raison).

## Usage

    python3 dev-hub/tools/check-phpunit-file-paths.py
    python3 dev-hub/tools/check-phpunit-file-paths.py --verbose
    python3 dev-hub/tools/check-phpunit-file-paths.py --self-test
    python3 dev-hub/tools/check-phpunit-file-paths.py --coverage-only

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
WORKFLOWS_DIR = REPO_ROOT / ".github" / "workflows"

FILE_ENTRY = re.compile(r"<file>\s*([^<]+?)\s*</file>")
DIR_ENTRY = re.compile(r"<directory[^>]*>\s*([^<]+?)\s*</directory>")
SUITE_BLOCK = re.compile(r"<testsuite\s+name=\"([^\"]+)\"[^>]*>(.*?)</testsuite>", re.S)
# `--testsuite=Nom` ou `--testsuite Nom` dans un workflow.
CI_SUITE = re.compile(r"--testsuite[= ]([A-Za-z0-9_]+)")

# Fichiers legitimement hors de toute suite executee. Chaque entree doit porter
# une RAISON : une allowlist sans justification devient un cimetiere.
COVERAGE_ALLOWLIST: dict[str, str] = {}


# ---------------------------------------------------------------------------
# A. Sens « liste -> disque »
# ---------------------------------------------------------------------------


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
# B. Sens « disque -> liste »
# ---------------------------------------------------------------------------


def parse_suites(phpunit_path: Path) -> dict[str, tuple[list[str], list[str]]]:
    """{nom_suite: (fichiers, repertoires)} — chemins normalises en `/`."""
    text = phpunit_path.read_text(encoding="utf-8", errors="replace")
    suites: dict[str, tuple[list[str], list[str]]] = {}

    for match in SUITE_BLOCK.finditer(text):
        name, body = match.group(1), match.group(2)
        files = [f.replace("\\", "/") for f in FILE_ENTRY.findall(body)]
        dirs = [d.replace("\\", "/").rstrip("/") for d in DIR_ENTRY.findall(body)]
        suites[name] = (files, dirs)

    return suites


def ci_executed_suites(workflows_dir: Path) -> set[str]:
    """Testsuites reellement lancees par la CI, lues dans les workflows.

    Derivee et non recopiee : si la CI change de suite, la garde suit. Un
    workflow absent (checkout partiel) donne un ensemble vide, et la garde
    signale alors TOUS les fichiers — fail-closed, jamais fail-open.
    """
    if not workflows_dir.is_dir():
        return set()

    found: set[str] = set()
    for workflow in sorted(workflows_dir.glob("*.yml")):
        text = workflow.read_text(encoding="utf-8", errors="replace")
        # Ignorer les lignes de commentaire : un `--testsuite=` cite dans un
        # commentaire documentaire ne prouve pas une execution.
        for line in text.splitlines():
            if line.lstrip().startswith("#"):
                continue
            found.update(CI_SUITE.findall(line))

    return found


def analyse_coverage(
    phpunit_path: Path,
    api_root: Path,
    executed: set[str],
) -> tuple[list[str], list[str], int]:
    """Retourne (orphelins, suites_executees_inconnues, total_fichiers)."""
    suites = parse_suites(phpunit_path)

    unknown = sorted(s for s in executed if s not in suites)

    covered: set[str] = set()
    for name in executed:
        if name not in suites:
            continue
        files, dirs = suites[name]
        covered.update(files)
        for directory in dirs:
            target = api_root / directory
            if not target.is_dir():
                continue
            for path in target.rglob("*Test.php"):
                covered.add(path.relative_to(api_root).as_posix())

    all_tests = {
        p.relative_to(api_root).as_posix()
        for p in (api_root / "tests").rglob("*Test.php")
    }

    orphans = sorted(t for t in all_tests - covered if t not in COVERAGE_ALLOWLIST)

    return orphans, unknown, len(all_tests)


# ---------------------------------------------------------------------------
# Autotest : prouve que la garde attrape chaque forme de defaut.
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

# (nom, phpunit.xml, fichiers presents sur disque, suites executees, orphelins attendus)
COVERAGE_SELF_TEST_CASES: list[tuple[str, str, list[str], set[str], int]] = [
    (
        "fichier orphelin (declare nulle part)",
        """<phpunit><testsuites>
        <testsuite name="A"><file>tests/Feature/CoveredTest.php</file></testsuite>
        </testsuites></phpunit>""",
        ["tests/Feature/CoveredTest.php", "tests/Feature/OrphanTest.php"],
        {"A"},
        1,
    ),
    (
        "fichier couvert par <directory>",
        """<phpunit><testsuites>
        <testsuite name="A"><directory>tests/Feature</directory></testsuite>
        </testsuites></phpunit>""",
        ["tests/Feature/Deep/NestedTest.php"],
        {"A"},
        0,
    ),
    (
        "suite declaree mais NON executee par la CI (piege principal)",
        """<phpunit><testsuites>
        <testsuite name="Feature"><directory>tests/Feature</directory></testsuite>
        <testsuite name="A"><file>tests/Feature/CoveredTest.php</file></testsuite>
        </testsuites></phpunit>""",
        ["tests/Feature/CoveredTest.php", "tests/Feature/OnlyInFeatureSuiteTest.php"],
        {"A"},
        1,
    ),
    (
        "aucune suite executee (fail-closed)",
        """<phpunit><testsuites>
        <testsuite name="A"><file>tests/Feature/CoveredTest.php</file></testsuite>
        </testsuites></phpunit>""",
        ["tests/Feature/CoveredTest.php"],
        set(),
        1,
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
                f"  [{status}] A/{name} -> {len(found)} violation(s), "
                f"attendu {'>=1' if should_fail else '0'}"
            )
            if not ok:
                failures += 1

    for name, xml, files, executed, expected in COVERAGE_SELF_TEST_CASES:
        with tempfile.TemporaryDirectory() as tmp:
            api_root = Path(tmp)
            for rel in files:
                target = api_root / rel
                target.parent.mkdir(parents=True, exist_ok=True)
                target.write_text("<?php\n")
            phpunit = api_root / "phpunit.xml"
            phpunit.write_text(xml)

            orphans, _unknown, _total = analyse_coverage(phpunit, api_root, executed)
            ok = len(orphans) == expected
            status = "OK  " if ok else "FAIL"
            print(
                f"  [{status}] B/{name} -> {len(orphans)} orphelin(s), attendu {expected}"
            )
            if not ok:
                failures += 1

    total_cases = len(SELF_TEST_CASES) + len(COVERAGE_SELF_TEST_CASES)
    if failures:
        print(f"\nAUTOTEST ECHEC — {failures} cas sur {total_cases}.", file=sys.stderr)
        return 1
    print(f"\nAUTOTEST OK — {total_cases} cas.")
    return 0


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--verbose", action="store_true")
    parser.add_argument("--self-test", action="store_true")
    parser.add_argument("--coverage-only", action="store_true")
    parser.add_argument("--phpunit", default=str(DEFAULT_PHPUNIT))
    parser.add_argument("--api-root", default=str(API_ROOT))
    parser.add_argument("--workflows", default=str(WORKFLOWS_DIR))
    args = parser.parse_args()

    if args.self_test:
        return self_test()

    phpunit_path = Path(args.phpunit)
    api_root = Path(args.api_root)
    workflows_dir = Path(args.workflows)

    if not phpunit_path.is_file():
        print(f"ERREUR — fichier introuvable : {phpunit_path}", file=sys.stderr)
        return 2

    failed = False

    # --- A. liste -> disque -------------------------------------------------
    if not args.coverage_only:
        violations = analyse(phpunit_path, api_root)
        total = len(FILE_ENTRY.findall(phpunit_path.read_text(encoding="utf-8", errors="replace")))

        for line, message in violations:
            print(f"{phpunit_path.name}:{line}: {message}", file=sys.stderr)

        if args.verbose:
            print(f"A/ Entrees <file> analysees : {total}")

        if violations:
            print("", file=sys.stderr)
            print(
                f"ECHEC A — {len(violations)} entree(s) <file> invalide(s) sur {total}. "
                f"Regenerer : php api/dev-tools/generate-feature-shards.php",
                file=sys.stderr,
            )
            failed = True
        else:
            print(f"OK A — {total} entrees <file> resolvent un fichier reel.")

    # --- B. disque -> liste -------------------------------------------------
    executed = ci_executed_suites(workflows_dir)
    orphans, unknown, total_tests = analyse_coverage(phpunit_path, api_root, executed)

    if args.verbose:
        print(f"B/ Suites executees par la CI : {', '.join(sorted(executed)) or '(aucune)'}")
        print(f"B/ Fichiers de test sur disque : {total_tests}")

    for name in unknown:
        print(
            f"AVERTISSEMENT — la CI lance --testsuite={name}, absente de {phpunit_path.name} : "
            f"elle ne selectionne aucun test",
            file=sys.stderr,
        )

    if orphans:
        print("", file=sys.stderr)
        print(
            f"ECHEC B — {len(orphans)} fichier(s) de test ne sont executes par AUCUNE "
            f"testsuite lancee en CI :",
            file=sys.stderr,
        )
        for orphan in orphans:
            print(f"  {orphan}", file=sys.stderr)
        print(
            "\nRegenerer : python3 dev-hub/tools/generate-feature-mvp-split.py "
            "&& php api/dev-tools/generate-feature-shards.php",
            file=sys.stderr,
        )
        failed = True
    else:
        print(
            f"OK B — les {total_tests} fichiers de test sont couverts par une suite executee en CI."
        )

    return 1 if failed else 0


if __name__ == "__main__":
    sys.exit(main())
