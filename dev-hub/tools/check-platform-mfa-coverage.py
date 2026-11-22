#!/usr/bin/env python3
"""Issue #9810 — garde CI : tout groupe `auth:super_admin_api` porte `platform.mfa`.

## Pourquoi cette garde existe

`EnsurePlatformMfaEnrolled` (#9096) impose la MFA aux comptes de plateforme. Il
est declare comme une garde GLOBALE, mais il n'est applique que la ou on l'a
ecrit dans les routes. Mesure sur `main` avant ce correctif :

    routes/api.php              675  MFA=OUI  prefix=platform
    routes/api.php              903  MFA=OUI  prefix=admin
    routes/modules/billing.php  144  MFA=NON  prefix=platform/growth

Le troisieme groupe porte des actes FINANCIERS (taux de commission, statut de
versement) et laissait passer un super-admin sans second facteur.

## Ce que la garde refuse

Tout `Route::middleware([...])` (ou `->middleware([...])`) dont la liste
contient `auth:super_admin_api` mais PAS `platform.mfa`.

## Ce qu'elle ne fait pas

Elle ne verifie pas que le middleware fait bien son travail (c'est le role de
`LastAdministratorAndPlatformMfaTest`). Elle verifie qu'il est CABLE — c'est
exactement le trou : le middleware etait correct, il n'etait pas branche.

## Usage

    python3 dev-hub/tools/check-platform-mfa-coverage.py
    python3 dev-hub/tools/check-platform-mfa-coverage.py --self-test
"""

from __future__ import annotations

import argparse
import re
import sys
from pathlib import Path

REPO_ROOT = Path(__file__).resolve().parents[2]
ROUTES_DIR = REPO_ROOT / "api" / "routes"

GUARD = "auth:super_admin_api"
MFA = "platform.mfa"

# Exemptions EXPLICITES et motivees. Une exemption n'est pas un oubli : c'est
# une decision ecrite, verifiable en revue. Toute nouvelle entree doit dire
# POURQUOI la MFA n'a pas de sens sur cette route.
EXEMPTIONS: dict[str, str] = {
    # `GET /api/v1/metrics` est consomme par la supervision (scrapers, sondes
    # externes) avec un jeton de service. Un scraper ne peut pas saisir un code
    # TOTP : exiger la MFA ici couperait la supervision, donc l'alerte, donc la
    # capacite a voir une panne. La route est deja protegee par un jeton
    # super-admin dedie + `throttle:metrics` (anti-fingerprinting, #1466).
    "api/routes/api.php:129": "endpoint de supervision machine (scrapers) — pas de TOTP possible",
}

# Un appel de middleware : `Route::middleware([...])` ou `->middleware([...])`.
# On capture la liste entre crochets, sur une ou plusieurs lignes.
MIDDLEWARE_CALL = re.compile(
    r"(?:Route::middleware|->middleware)\s*\(\s*\[(.*?)\]\s*\)",
    re.DOTALL,
)

# Un appel de middleware a argument unique : `->middleware('platform.mfa')`.
MIDDLEWARE_SINGLE = re.compile(
    r"(?:Route::middleware|->middleware)\s*\(\s*'([^']+)'\s*\)",
)


def line_of(text: str, index: int) -> int:
    return text.count("\n", 0, index) + 1


def scan_file(path: Path) -> list[tuple[int, str]]:
    """Retourne [(ligne, extrait)] des groupes super_admin sans MFA."""
    text = path.read_text(encoding="utf-8", errors="ignore")
    problems: list[tuple[int, str]] = []

    for match in MIDDLEWARE_CALL.finditer(text):
        body = match.group(1)
        if GUARD not in body:
            continue
        if MFA in body:
            continue
        problems.append((line_of(text, match.start()), body.strip().replace("\n", " ")[:120]))

    # Un groupe peut aussi etre declare avec un seul middleware puis completer
    # par `->middleware('platform.mfa')` : on ne signale que si la MFA n'apparait
    # nulle part dans les 3 lignes qui suivent la declaration.
    for match in MIDDLEWARE_SINGLE.finditer(text):
        if match.group(1) != GUARD:
            continue
        start = match.start()
        window = text[start : start + 400]
        if MFA in window:
            continue
        problems.append((line_of(text, start), f"middleware('{GUARD}') sans {MFA} a proximite"))

    return problems


def run(verbose: bool = False) -> int:
    if not ROUTES_DIR.is_dir():
        print(f"ERREUR : {ROUTES_DIR} introuvable.", file=sys.stderr)
        return 2

    files = sorted(ROUTES_DIR.rglob("*.php"))
    total_groups = 0
    failures: list[tuple[Path, int, str]] = []

    for path in files:
        text = path.read_text(encoding="utf-8", errors="ignore")
        total_groups += text.count(GUARD)
        for line, snippet in scan_file(path):
            rel = str(path.relative_to(REPO_ROOT))
            key = f"{rel}:{line}"
            if key in EXEMPTIONS:
                if verbose:
                    print(f"  [exempte] {key} — {EXEMPTIONS[key]}")
                continue
            failures.append((path, line, snippet))

    if verbose:
        print(f"Fichiers de routes analyses : {len(files)}")
        print(f"Occurrences de {GUARD} : {total_groups}")

    if failures:
        print(f"ECHEC : {len(failures)} groupe(s) {GUARD} sans {MFA} :", file=sys.stderr)
        for path, line, snippet in failures:
            rel = path.relative_to(REPO_ROOT)
            print(f"  {rel}:{line}  {snippet}", file=sys.stderr)
        print(
            "\nAjouter 'platform.mfa' a la liste de middleware du groupe "
            "(voir EnsurePlatformMfaEnrolled, #9096 / #9810).",
            file=sys.stderr,
        )
        return 1

    print(f"OK : tous les groupes {GUARD} portent {MFA} ({total_groups} occurrence(s)).")
    return 0


def self_test() -> int:
    """Verifie que le detecteur attrape le cas reel et laisse passer le bon."""
    import tempfile

    cases = [
        # (contenu, nombre d'echecs attendu, description)
        (
            "Route::middleware(['auth:super_admin_api', 'throttle:x'])->prefix('a')->group(function () {});",
            1,
            "groupe sans MFA -> doit echouer",
        ),
        (
            "Route::middleware(['auth:super_admin_api', 'platform.mfa', 'throttle:x'])->prefix('a')->group(function () {});",
            0,
            "groupe avec MFA -> doit passer",
        ),
        (
            "Route::middleware(['auth:sanctum', 'tenant'])->group(function () {});",
            0,
            "groupe non plateforme -> ignore",
        ),
        (
            "Route::middleware([\n    'auth:super_admin_api',\n    'throttle:x',\n])->group(function () {});",
            1,
            "liste multiligne sans MFA -> doit echouer",
        ),
        (
            "Route::middleware([\n    'auth:super_admin_api',\n    'platform.mfa',\n])->group(function () {});",
            0,
            "liste multiligne avec MFA -> doit passer",
        ),
    ]

    failed = 0
    with tempfile.TemporaryDirectory() as tmp:
        for index, (content, expected, description) in enumerate(cases):
            path = Path(tmp) / f"case_{index}.php"
            path.write_text(content, encoding="utf-8")
            found = len(scan_file(path))
            ok = found == expected
            status = "OK  " if ok else "ECHEC"
            print(f"  [{status}] {description} (attendu {expected}, trouve {found})")
            if not ok:
                failed += 1

    if failed:
        print(f"\nAUTOTEST ECHOUE : {failed} cas.", file=sys.stderr)
        return 1

    print(f"\nAUTOTEST OK : {len(cases)} cas.")
    return 0


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--verbose", action="store_true")
    parser.add_argument("--self-test", action="store_true")
    args = parser.parse_args()

    if args.self_test:
        return self_test()

    return run(verbose=args.verbose)


if __name__ == "__main__":
    sys.exit(main())
