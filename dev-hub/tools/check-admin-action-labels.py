#!/usr/bin/env python3
"""check-admin-action-labels.py — garde CI « actions de ligne & i18n » (issue #7434).

Règle posée par le propriétaire le 2026-09-14 : « si tout est icône, pourquoi
lui reste-t-il son texte ? Les seules choses qui peuvent rester icône **et**
texte, c'est le menu ». La console d'admin doit donc :

1. **Une seule convention d'action de ligne** — `RowActionButton` (icône seule
   + `title` + `aria-label` + `sr-only`), jamais un bouton texte « Modifier » /
   « Supprimer » dans une cellule d'actions ;
2. **Nom accessible garanti** — un `<button>` icône seule, ou un
   `RowActionButton` sans `:label`, rend l'action invisible au lecteur d'écran ;
3. **Aucun libellé d'action en dur** — les en-têtes de table et les libellés
   d'action passent par le catalogue i18n (4 locales), sinon un changement de
   locale laisse des mots anglais/français dans l'interface.

Ce que la garde vérifie concrètement :

- A. Aucun nœud de texte de template égal à un libellé d'action connu
  (`Actions`, `Edit`, `Delete`, `Modifier`, `Supprimer`, …) : ces libellés
  doivent venir de `t(...)`/`$t(...)`.
- B. Dans chaque bloc `<template #row-actions>`, tout élément d'action est soit
  un `<RowActionButton>` porteur d'un `:label`, soit un `<button>` porteur d'un
  `aria-label` ou d'un contenu textuel (interpolé ou littéral).

Usage :
    python3 dev-hub/tools/check-admin-action-labels.py [root]

Exit 0 = conforme ; exit 1 = violation (messages ::error:: pour GitHub Actions).
"""

from __future__ import annotations

import re
import sys
from pathlib import Path

SRC_REL = "front/admin-dashboard/src"

# Libellés d'action historiquement écrits en dur (FR + EN).
ACTION_WORDS = [
    "Actions",
    "Edit",
    "Delete",
    "Modifier",
    "Supprimer",
    "Activer",
    "Désactiver",
    "Activate",
    "Deactivate",
]
TEXT_NODE_RE = re.compile(r">\s*(" + "|".join(ACTION_WORDS) + r")\s*<")
SCRIPT_RE = re.compile(r"<script\b.*?</script>", re.S)
ROW_ACTIONS_RE = re.compile(r"<template\s+#row-actions\b[^>]*>(.*?)</template>", re.S)
BUTTON_RE = re.compile(r"<button\b((?:[^>\"]|\"[^\"]*\")*?)>", re.S)
ROW_ACTION_BTN_RE = re.compile(r"<RowActionButton\b((?:[^>\"]|\"[^\"]*\")*?)/?>", re.S)


def line_of(source: str, index: int) -> int:
    return source.count("\n", 0, index) + 1


def button_body(block: str, start: int) -> str:
    end = block.find("</button>", start)
    return block[start:end] if end != -1 else ""


def visible_text(fragment: str) -> str:
    return re.sub(r"<[^>]*>", "", fragment).strip()


def check_file(path: Path, rel: str) -> list[str]:
    errors: list[str] = []
    source = path.read_text(encoding="utf-8")
    template = SCRIPT_RE.sub("", source)

    # A. libellés d'action en dur
    for match in TEXT_NODE_RE.finditer(template):
        errors.append(
            f'{rel}:{line_of(template, match.start())} — libellé d\'action en dur '
            f'« {match.group(1)} » : le passer par t(\'…\') / $t(\'…\') (issue #7434).'
        )

    # B. nom accessible des actions de ligne
    for row in ROW_ACTIONS_RE.finditer(template):
        block = row.group(1)
        base = row.start(1)

        for btn in ROW_ACTION_BTN_RE.finditer(block):
            if ":label" not in btn.group(1):
                errors.append(
                    f"{rel}:{line_of(template, base + btn.start())} — RowActionButton sans "
                    ":label : l'action n'a pas de nom accessible (issue #7434)."
                )

        for btn in BUTTON_RE.finditer(block):
            attrs = btn.group(1)
            body = button_body(block, btn.end())
            if "aria-label" in attrs or "v-bind=" in attrs:
                continue
            if visible_text(body) or "{{" in body:
                continue
            errors.append(
                f"{rel}:{line_of(template, base + btn.start())} — bouton icône seule sans "
                "aria-label dans une cellule d'actions : utiliser RowActionButton "
                "(issue #7434)."
            )

    return errors


def main() -> int:
    root = Path(sys.argv[1]).resolve() if len(sys.argv) > 1 else Path(__file__).resolve().parents[2]
    base = root / SRC_REL
    if not base.is_dir():
        print(f"OK — {SRC_REL} absent : garde admin sans objet (fixtures ?).")
        return 0

    errors: list[str] = []
    for path in sorted(base.rglob("*.vue")):
        errors.extend(check_file(path, path.relative_to(root).as_posix()))

    if errors:
        for err in errors:
            print(f"::error::Admin {err}", file=sys.stderr)
        print(
            f"::error::Garde actions/labels admin : {len(errors)} violation(s) (issue #7434).",
            file=sys.stderr,
        )
        return 1

    print("OK — admin : une seule convention d'action de ligne, libellés i18n (issue #7434).")
    return 0


if __name__ == "__main__":
    sys.exit(main())
