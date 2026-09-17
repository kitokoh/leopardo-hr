#!/usr/bin/env python3
"""check-double-docblock.py — refuser deux docblocks consécutifs avant une
déclaration PHP (issue #7582).

Le défaut
---------
En PHP, **seul le DERNIER docblock** placé avant une déclaration compte. Un
docblock porteur d'une annotation (`@extends`, `@property`, `@param`,
`@return`, `@mixin`…) suivi d'un second docblock voit donc son annotation
**silencieusement ignorée** par PHPStan.

C'est invisible à la lecture rapide et ne casse rien à l'exécution : le pire
profil de défaut. Constat mesuré (#7582, `EmployeeFactory`) : l'annotation
`@extends Factory<Employee>` placée dans un docblock séparé était ignorée,
`Employee::factory()->create()` était typé `Illuminate\\Database\\Eloquent\\Model`,
et **≈20 erreurs** du check requis « PHPStan — Strict » apparaissaient
(`should return array{Company, Employee} but returns array{Company, Model}`).

Ce que la garde vérifie
----------------------
Une déclaration (classe, interface, trait, enum, méthode, propriété, constante)
ne doit pas être immédiatement précédée de deux commentaires `/** … */`, séparés
par des blancs uniquement.

Deux modes :

* par défaut — **bloque sur les NOUVELLES occurrences** et affiche la dette
  existante (comptée, non bloquante). C'est la doctrine du dépôt pour une dette
  large qu'on résorbe module par module (cf. `check-duplicate-schema-create.py`
  de la tranche #7455) : une garde qui rougit sur tout le dépôt existant est
  désarmée dès le premier jour, on ne peut plus la distinguer du bruit.
* `--strict` — bloque sur **toute** occurrence (à activer quand la dette est à
  zéro, ou sur un module déjà assaini).

Usage :
    python3 dev-hub/tools/check-double-docblock.py [--base <ref>] [--strict] [--audit]
"""

from __future__ import annotations

import argparse
import re
import subprocess
import sys
from pathlib import Path

DOCBLOCK = re.compile(r"/\*\*.*?\*/", re.S)
TAG = re.compile(r"@([a-zA-Z][a-zA-Z0-9-]*)")
# Extensions PHP suivies par le dépôt.
PHP_SUFFIX = ".php"


def is_only_whitespace(text: str) -> bool:
    return text.strip() == ""


def scan_source(text: str) -> list[tuple[int, set[str], set[str]]]:
    """Retourne [(offset_fin_du_1er_bloc, tags_1er, tags_2e)] pour chaque paire."""
    blocks = [(m.start(), m.end(), m.group(0)) for m in DOCBLOCK.finditer(text)]
    found = []
    for i in range(len(blocks) - 1):
        end_current, start_next = blocks[i][1], blocks[i + 1][0]
        if not is_only_whitespace(text[end_current:start_next]):
            continue
        first_tags = set(TAG.findall(blocks[i][2]))
        if not first_tags:
            # Deux docblocks sans annotation : contraire au style, mais aucune
            # annotation n'est désarmée — hors du défaut visé par #7582.
            continue
        found.append((blocks[i][1], first_tags, set(TAG.findall(blocks[i + 1][2]))))
    return found


def scan_file(path: Path) -> list[tuple[int, int, set[str], set[str]]]:
    try:
        text = path.read_text(encoding="utf-8", errors="ignore")
    except OSError:
        return []
    out = []
    for end_offset, first_tags, second_tags in scan_source(text):
        line = text.count("\n", 0, end_offset) + 1
        out.append((line, end_offset, first_tags, second_tags))
    return out


def git(root: Path, *args: str) -> str:
    return subprocess.run(
        ["git", "-C", str(root), *args], capture_output=True, text=True, check=True
    ).stdout


def tracked_php_files(root: Path) -> list[Path]:
    raw = subprocess.run(
        ["git", "-C", str(root), "ls-files", "-z", "*.php"],
        capture_output=True,
        text=True,
        check=True,
    ).stdout
    return [root / rel for rel in raw.split("\0") if rel]


def head_version(root: Path, ref: str, rel: str) -> str | None:
    """Contenu du fichier dans `ref` (None s'il n'y existe pas)."""
    proc = subprocess.run(
        ["git", "-C", str(root), "show", f"{ref}:{rel}"],
        capture_output=True,
        text=True,
    )
    return proc.stdout if proc.returncode == 0 else None


def scan_ref(root: Path, ref: str) -> dict[str, int]:
    """Nombre d'occurrences par fichier dans une ref git."""
    # PAS de pathspec `*.php` ici : `git ls-tree` ne le développe pas à travers
    # les répertoires (il rend 0 fichier), là où `git ls-files` le fait. On
    # liste donc tout et on filtre en Python — même filtre que l'arbre courant.
    names = git(root, "ls-tree", "-r", "--name-only", ref).splitlines()
    counts: dict[str, int] = {}
    for rel in names:
        if not rel.endswith(PHP_SUFFIX):
            continue
        blob = head_version(root, ref, rel)
        if blob is None:
            continue
        n = len(scan_source(blob))
        if n:
            counts[rel] = n
    return counts


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--base", default="origin/main", help="ref de comparaison")
    parser.add_argument("--strict", action="store_true", help="bloquer sur toute occurrence")
    parser.add_argument("--audit", action="store_true", help="afficher l'inventaire complet")
    args = parser.parse_args()

    root = Path(__file__).resolve().parents[2]

    current: dict[str, list] = {}
    for path in tracked_php_files(root):
        if not path.is_file() or path.suffix != PHP_SUFFIX:
            continue
        hits = scan_file(path)
        if hits:
            current[str(path.relative_to(root))] = hits

    total = sum(len(v) for v in current.values())

    if args.audit:
        print(f"Inventaire des docblocks doubles porteurs d'annotation : {total}")
        for rel in sorted(current):
            for line, _off, first, second in current[rel]:
                print(f"  {rel}:{line}  1er={sorted(first)}  2e={sorted(second) or '(aucun)'}")
        return 0

    try:
        baseline = scan_ref(root, args.base)
    except subprocess.CalledProcessError:
        print(
            f"ERREUR : ref de comparaison introuvable ({args.base}). "
            "Faire `git fetch origin main` ou passer --base.",
            file=sys.stderr,
        )
        return 2

    if args.strict:
        if total:
            print(f"ECHEC : {total} docblock(s) double(s) porteur(s) d'annotation.")
            for rel in sorted(current):
                for line, _off, first, second in current[rel]:
                    print(f"  {rel}:{line}  1er={sorted(first)}  2e={sorted(second) or '(aucun)'}")
            return 1
        print("Aucun docblock double porteur d'annotation.")
        return 0

    # Mode par défaut : ne bloquer QUE ce qui est nouveau.
    new_offenders: list[str] = []
    for rel, hits in current.items():
        before = baseline.get(rel, 0)
        if len(hits) > before:
            new_offenders.append(
                f"{rel} : {before} -> {len(hits)} occurrence(s) "
                f"(ligne(s) {', '.join(str(h[0]) for h in hits)})"
            )

    if new_offenders:
        print("ECHEC : de nouvelles annotations PHPDoc sont desarmees (#7582).")
        print(
            "En PHP, seul le DERNIER docblock avant la declaration compte : une\n"
            "annotation placee dans un docblock separe est ignoree par PHPStan.\n"
            "Corrigez en gardant UN SEUL docblock — annotations dans celui qui\n"
            "decrit la classe/la methode (doctrine #7499, EmployeeFactory).\n"
        )
        for line in new_offenders:
            print(f"  {line}")
        print(
            f"\nDette existante (non bloquante, informe) : "
            f"{sum(len(v) for v in current.values())} occurrence(s). "
            "Inventaire : --audit ; blocage total : --strict."
        )
        return 1

    print(
        f"Aucune nouvelle annotation desarmee. "
        f"(dette existante : {total} occurrence(s), inchangee ou en baisse)"
    )
    return 0


if __name__ == "__main__":
    sys.exit(main())
