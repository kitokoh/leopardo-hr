#!/usr/bin/env python3
"""check-admin-row-actions.py — garde « action de ligne » de la console admin (issue #7434).

Règle posée par le propriétaire le 2026-09-14 :

    « si tout est icône, pourquoi lui reste-t-il son texte ? Les seules choses
      qui peuvent rester icône ET texte, c'est le menu. »

Conséquence pour la console admin (front/admin-dashboard) :

  1. une action de LIGNE (slot `#row-actions` d'un tableau) est une **icône
     seule** — jamais un bouton texte « Modifier » / « Supprimer » ;
  2. cette icône porte TOUJOURS un nom accessible (`aria-label` + `title`),
     sans quoi elle est invisible au lecteur d'écran ;
  3. l'en-tête de colonne d'actions est **traduit** (`$t('common.actions')`) et
     non écrit en clair — la console est affichée en 4 locales.

Le composant de référence est `src/components/common/RowActionButton.vue`.

Usage :
    python3 dev-hub/tools/check-admin-row-actions.py [--strict] [racine]

  * sans `--strict` : RAPPORT (exit 0) — la dette restante est listée, elle ne
    bloque pas les autres PR (même régime que `check-crm-branch-protocol.sh`) ;
  * avec `--strict` : exit 1 dès la première violation — à activer quand la
    conversion de la console est terminée.

Ce garde est branché dans `web-ci.yml` (job `web-lint`), déjà filtré sur
`front/admin-dashboard/**` : aucun runner supplémentaire n'est consommé.
"""

from __future__ import annotations

import argparse
import re
import sys
from pathlib import Path

SRC_REL = Path("front/admin-dashboard/src")

# Un libellé visible dans une action de ligne : interpolation i18n ou texte brut.
INTERPOLATION = re.compile(r"\{\{.*?\}\}", re.S)
TAG = re.compile(r"<[^>]+>", re.S)
BUTTON = re.compile(r"<(button|router-link|a)\b([^>]*)>(.*?)</\1>", re.S | re.I)
ROW_ACTIONS_OPEN = re.compile(r"<template\s+#row-actions\b", re.I)
TEMPLATE_OPEN = re.compile(r"<template\b", re.I)
TEMPLATE_CLOSE = re.compile(r"</template>", re.I)
HARDCODED_ACTIONS_HEADER = re.compile(r">\s*(Actions|Modifier|Supprimer|Edit|Delete)\s*<")


def row_action_blocks(text: str) -> list[tuple[int, str]]:
    """Retourne [(ligne_debut, contenu_du_bloc)] pour chaque slot #row-actions."""
    blocks: list[tuple[int, str]] = []
    lines = text.splitlines()
    for index, line in enumerate(lines):
        if not ROW_ACTIONS_OPEN.search(line):
            continue
        depth = 0
        chunk: list[str] = []
        for current in lines[index:]:
            depth += len(TEMPLATE_OPEN.findall(current)) - len(TEMPLATE_CLOSE.findall(current))
            chunk.append(current)
            if depth <= 0:
                break
        blocks.append((index + 1, "\n".join(chunk)))
    return blocks


def visible_label(inner: str) -> str:
    """Texte réellement rendu par le contenu d'un bouton (interpolations incluses)."""
    without_tags = TAG.sub(" ", inner)
    return " ".join(without_tags.split())


def check_file(path: Path) -> list[tuple[int, str]]:
    findings: list[tuple[int, str]] = []
    text = path.read_text(encoding="utf-8")

    for start, block in row_action_blocks(text):
        for match in BUTTON.finditer(block):
            attrs, inner = match.group(2), match.group(3)
            offset = block[: match.start()].count("\n")
            line = start + offset
            if visible_label(inner):
                findings.append(
                    (line, "action de ligne avec libellé VISIBLE → icône seule via RowActionButton.vue"),
                )
                continue
            if not re.search(r":?aria-label\s*=", attrs) or not re.search(r":?title\s*=", attrs):
                findings.append(
                    (line, "icône-action sans nom accessible → `aria-label` ET `title` obligatoires"),
                )

    for offset, line_text in enumerate(text.splitlines(), start=1):
        if HARDCODED_ACTIONS_HEADER.search(line_text) and "{{" not in line_text:
            findings.append(
                (offset, "en-tête/libellé d'action en dur → `$t('common.actions', 'Actions')`"),
            )

    return findings


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("root", nargs="?", default=".")
    parser.add_argument("--strict", action="store_true", help="exit 1 à la première violation")
    args = parser.parse_args()

    src = Path(args.root) / SRC_REL
    if not src.is_dir():
        print(f"::error::Console admin introuvable : {src}")
        return 1

    total = 0
    files = 0
    for path in sorted(src.rglob("*.vue")):
        findings = check_file(path)
        if not findings:
            continue
        files += 1
        total += len(findings)
        for line, message in findings:
            print(f"::warning file={path.relative_to(args.root)}::ligne {line} — {message}")

    if total == 0:
        print("OK — convention d'action de ligne respectée (icône seule, nom accessible, en-tête i18n).")
        return 0

    print(
        f"RAPPORT — {total} action(s) de ligne hors convention dans {files} fichier(s) "
        "(issue #7434 : composant unique RowActionButton.vue + aria-label + en-tête i18n)."
    )
    return 1 if args.strict else 0


if __name__ == "__main__":
    sys.exit(main())
