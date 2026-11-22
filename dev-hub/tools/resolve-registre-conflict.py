#!/usr/bin/env python3
"""Résout le conflit récurrent de docs/GESTION_PROJET/REGISTRE_SCENARIOS_TESTS.md.

Ce registre est un journal : chaque PR ajoute sa section EN TÊTE. Deux PR
ouvertes en même temps produisent donc toujours le même conflit — les deux
côtés ont inséré au même endroit. La bonne résolution n'est jamais « choisir
un côté » : c'est de garder les DEUX sections, la nôtre d'abord (elle est la
plus récente), puis celle de main.

Usage :
    python3 dev-hub/tools/resolve-registre-conflict.py [--check]

--check ne modifie rien et sort en 1 s'il reste un marqueur de conflit.
"""
from __future__ import annotations

import sys
from pathlib import Path

REGISTRE = Path("docs/GESTION_PROJET/REGISTRE_SCENARIOS_TESTS.md")
MARKERS = ("<<<<<<<", "=======", ">>>>>>>")


def has_markers(lines: list[str]) -> bool:
    return any(line.startswith(MARKERS) for line in lines)


def resolve(lines: list[str]) -> list[str]:
    """Garde les deux côtés de chaque bloc de conflit, le nôtre en premier."""
    out: list[str] = []
    i = 0
    while i < len(lines):
        line = lines[i]
        if not line.startswith("<<<<<<<"):
            out.append(line)
            i += 1
            continue

        # Bloc de conflit : <<<<<<< / ours / ======= / theirs / >>>>>>>
        i += 1
        ours: list[str] = []
        while i < len(lines) and not lines[i].startswith("======="):
            ours.append(lines[i])
            i += 1
        i += 1  # saute =======
        theirs: list[str] = []
        while i < len(lines) and not lines[i].startswith(">>>>>>>"):
            theirs.append(lines[i])
            i += 1
        i += 1  # saute >>>>>>>

        out.extend(ours)
        if ours and theirs:
            out.append("")
        out.extend(theirs)

    return out


def main() -> int:
    check_only = "--check" in sys.argv

    if not REGISTRE.exists():
        print(f"ERREUR : {REGISTRE} introuvable", file=sys.stderr)
        return 2

    lines = REGISTRE.read_text(encoding="utf-8").split("\n")

    if check_only:
        if has_markers(lines):
            print("CONFLIT : marqueurs présents dans le registre", file=sys.stderr)
            return 1
        print("OK : aucun marqueur de conflit")
        return 0

    if not has_markers(lines):
        print("OK : aucun marqueur de conflit, rien à faire")
        return 0

    resolved = resolve(lines)
    if has_markers(resolved):
        print("ERREUR : marqueurs restants après résolution", file=sys.stderr)
        return 1

    REGISTRE.write_text("\n".join(resolved), encoding="utf-8")
    print(f"OK : conflit résolu, les deux sections conservées ({len(resolved)} lignes)")
    return 0


if __name__ == "__main__":
    sys.exit(main())
