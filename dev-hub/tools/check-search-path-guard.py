#!/usr/bin/env python3
"""Issue #9115 — `SET search_path` doit toujours etre restaure.

`PlatformCompanyLookup::findOrFail()` posait `SET search_path TO public` sans
jamais restaurer l'etat d'entree. Sur une connexion reutilisee (worker de file
d'attente, PHP-FPM, test qui enchaine plusieurs appels), la session restait sur
`public` pour tout le reste du cycle : les tables tenant-scoped devenaient
introuvables (SQLSTATE 42P01 « relation ... does not exist »), puis toute
instruction suivante de la transaction echouait en cascade (25P02). Le job CI
`Backend` ne rendait alors aucun verdict : 5 h de timeout.

Le bug n'etait pas isole — le meme motif `DB::statement('SET search_path TO
<litteral>')` sans restauration apparaissait dans des dizaines de fichiers.

Ce garde detecte les occurrences **nouvelles**. Le stock existant est fige dans
`dev-hub/tools/search-path-guard-baseline.txt` (ratchet) : la dette ne peut que
diminuer, jamais augmenter. Corriger un fichier = le retirer de la baseline.

Usage :
    python3 dev-hub/tools/check-search-path-guard.py            # verifie
    python3 dev-hub/tools/check-search-path-guard.py --update    # fige la dette
"""

from __future__ import annotations

import argparse
import re
import subprocess
import sys
from pathlib import Path

REPO = Path(__file__).resolve().parents[2]
BASELINE = REPO / "dev-hub" / "tools" / "search-path-guard-baseline.txt"
SCAN_ROOT = "api/app/"

# Fichiers propriétaires désignés : ils portent des bascules de tenant
# *appariées* (pose dans une méthode, restauration dans une autre) qui ne
# peuvent pas être encapsulées dans un `finally` unique — la restauration est
# portée par un appel distinct (`setTenant()` / `resetToPrevious()`). Leur
# correction est couverte par les tests de TenantManager, pas par ce ratchet.
OWNED_FILES = {
    "api/app/Core/Tenant/TenantManager.php",
    # Le garde lui-meme : `set()` / `restore()` sont l'implementation unique du
    # SQL brut, et `run()` porte le `finally`. C'est le point d'audit.
    "api/app/Support/SearchPathGuard.php",
}

SET_LITERAL = re.compile(r"statement\(\s*(['\"])(SET\s+search_path[^'\"]*)\1", re.IGNORECASE)
# Une restauration lit une variable (l'etat sauvegarde), jamais un litteral.
RESTORE_VAR = re.compile(
    r"\$(?:previous|old|original|prev|saved|current|searchPath|search_path)\w*",
    re.IGNORECASE,
)


def _block_end(text: str, open_brace: int) -> int:
    depth = 0
    i = open_brace
    while i < len(text):
        if text[i] == "{":
            depth += 1
        elif text[i] == "}":
            depth -= 1
            if depth == 0:
                return i
        i += 1
    return len(text)


def _enclosing_function(text: str, pos: int) -> tuple[int, int] | None:
    start = text.rfind("function", 0, pos)
    if start < 0:
        return None
    brace = text.find("{", start)
    if brace < 0:
        return None
    return start, _block_end(text, brace)


def _inside_finally(text: str, pos: int) -> bool:
    for match in re.finditer(r"finally\s*\{", text):
        open_brace = match.end() - 1
        if open_brace <= pos < _block_end(text, open_brace):
            return True
    return False


def _restores_after(text: str, pos: int, end: int) -> bool:
    tail = text[pos:end]
    fin = tail.find("finally")
    if fin < 0:
        return False
    return "search_path" in tail[fin:]


def find_unsafe() -> list[tuple[str, int]]:
    files = subprocess.check_output(
        ["grep", "-rl", "SET search_path", SCAN_ROOT],
        cwd=REPO,
        text=True,
    ).split()

    unsafe: list[tuple[str, int]] = []
    for rel in files:
        path = REPO / rel
        if not path.is_file():
            continue
        text = path.read_text(encoding="utf-8", errors="replace")
        if rel in OWNED_FILES:
            continue
        for match in SET_LITERAL.finditer(text):
            argument = match.group(2)
            if RESTORE_VAR.search(argument):
                continue  # c'est une restauration, pas une pose
            if _inside_finally(text, match.start()):
                continue
            bounds = _enclosing_function(text, match.start())
            end = bounds[1] if bounds else len(text)
            if not _restores_after(text, match.start(), end):
                unsafe.append((rel, text[: match.start()].count("\n") + 1))
    return sorted(unsafe)


def key(entry: tuple[str, int]) -> str:
    return f"{entry[0]}:{entry[1]}"


def load_baseline() -> set[str]:
    if not BASELINE.is_file():
        return set()
    return {
        line.strip()
        for line in BASELINE.read_text(encoding="utf-8").splitlines()
        if line.strip() and not line.startswith("#")
    }


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--update", action="store_true", help="fige la dette courante")
    args = parser.parse_args()

    unsafe = find_unsafe()
    current = {key(entry) for entry in unsafe}

    if args.update:
        header = (
            "# Issue #9115 — occurrences de `SET search_path` litteral sans\n"
            "# restauration garantie. Dette figee : elle ne doit que DECROITRE.\n"
            "# Retirer une ligne quand le fichier passe a SearchPathGuard::run().\n"
        )
        BASELINE.write_text(
            header + "\n".join(sorted(current)) + "\n",
            encoding="utf-8",
        )
        print(f"baseline mise a jour : {len(current)} occurrence(s)")
        return 0

    baseline = load_baseline()
    new = sorted(current - baseline)
    fixed = sorted(baseline - current)

    if fixed:
        print(f"OK — {len(fixed)} occurrence(s) corrigee(s) :")
        for entry in fixed:
            print(f"   - {entry}")

    if new:
        print(
            f"ECHEC — {len(new)} occurrence(s) NOUVELLE(S) de `SET search_path` "
            "sans restauration :",
            file=sys.stderr,
        )
        for entry in new:
            print(f"   {entry}", file=sys.stderr)
        print(
            "\nUtiliser App\\Support\\SearchPathGuard::run($path, fn () => ...) "
            "qui restaure l'etat d'entree dans un finally.",
            file=sys.stderr,
        )
        return 1

    print(f"OK — aucune nouvelle occurrence (dette figee : {len(baseline)})")
    return 0


if __name__ == "__main__":
    sys.exit(main())
