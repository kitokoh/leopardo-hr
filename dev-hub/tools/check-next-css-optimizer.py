#!/usr/bin/env python3
"""check-next-css-optimizer.py — garde « optimizeCss ⇒ dépendance réellement utile » (#7531).

Constats mesurés (2026-09-16) :

1. `front/web/next.config.ts` active `experimental.optimizeCss`, mais `critters`
   — le paquet que Next requiert pour cette expérience — n'était déclaré **nulle
   part** (ni `package.json`, ni `package-lock.json`). Un `npm ci` sur un clone
   neuf ne l'installe donc pas.
2. Dans Next 16.3.4, `critters` n'est requis QUE par le chemin **Pages Router**
   (`server/render.js` → `server/post-process.js`, `pages/_document.js`) : il
   n'existe **aucune** occurrence de `optimizeCss`/`critters` dans
   `server/app-render/**`. Or `front/web` est **App Router uniquement**.
   Conséquence : l'expérience n'optimise rien ici, mais elle faisait échouer le
   `require` du document d'erreur intégré (Pages) — toute erreur devenait
   « Cannot find module 'critters' » (symptôme #7531).
3. Le paquet `critters` est déprécié (maintenance reprise sous `beasties`).
4. Cette expérience avait déjà été retirée une fois pour un problème de build
   (`docs/qa-expert14-session-2026-08-15.md`, issue #3984).

Règle de la garde : si une application Next active `optimizeCss`, alors elle doit
pouvoir l'exécuter — c'est-à-dire **avoir un Pages Router** ET déclarer
`critters`. Sinon la garde échoue et dit quoi faire (`optimizeCss: false`, ou
déclarer la dépendance si un Pages Router existe réellement).

Usage :
    python3 dev-hub/tools/check-next-css-optimizer.py [racine] [--self-test]
"""

from __future__ import annotations

import argparse
import json
import re
import shutil
import sys
import tempfile
from pathlib import Path

CONFIG_GLOBS = ("next.config.ts", "next.config.mjs", "next.config.js", "next.config.cjs")
ENABLED = re.compile(r"optimizeCss\s*:\s*(true|process\.env\.[\w]+\s*===\s*'true')", re.I)
DISABLED = re.compile(r"optimizeCss\s*:\s*(false|process\.env\.[\w]+\s*===\s*'false')", re.I)


def is_enabled(text: str) -> bool:
    """`optimizeCss` est-il activé sans être ensuite désactivé ?"""
    if not re.search(r"optimizeCss", text):
        return False
    # La dernière déclaration gagne (un fichier peut porter un commentaire
    # d'historique puis la valeur réelle).
    positions = [(m.start(), True) for m in ENABLED.finditer(text)]
    positions += [(m.start(), False) for m in DISABLED.finditer(text)]
    if not positions:
        return True  # présent sous une forme non littérale : on refuse par défaut
    return max(positions)[1]


PAGES_SUFFIXES = (".js", ".jsx", ".ts", ".tsx")


def has_pages_router(app_dir: Path) -> bool:
    """Un Pages Router existe-t-il ? (`Path.glob` ne gère pas `{js,jsx}`.)"""
    for candidate in ("pages", "src/pages"):
        path = app_dir / candidate
        if not path.is_dir():
            continue
        if any(child.suffix in PAGES_SUFFIXES for child in path.rglob("*") if child.is_file()):
            return True
    return False


def declares_critters(app_dir: Path) -> bool:
    package = app_dir / "package.json"
    if not package.is_file():
        return False
    try:
        data = json.loads(package.read_text(encoding="utf-8"))
    except json.JSONDecodeError:
        return False
    for section in ("dependencies", "devDependencies", "optionalDependencies"):
        if "critters" in (data.get(section) or {}):
            return True
    return False


def check(root: Path) -> list[str]:
    problems: list[str] = []
    for app_dir in sorted(root.rglob("package.json")):
        if "node_modules" in app_dir.parts:
            continue
        directory = app_dir.parent
        config = next((directory / name for name in CONFIG_GLOBS if (directory / name).is_file()), None)
        if config is None:
            continue
        text = config.read_text(encoding="utf-8", errors="replace")
        if not is_enabled(text):
            continue

        rel = config.relative_to(root)
        if not has_pages_router(directory):
            problems.append(
                f"{rel} active `experimental.optimizeCss` alors que {directory.relative_to(root)}/"
                " est App Router (aucun Pages Router) : l'expérience n'a aucun effet ici et son "
                "`require('critters')` (chemin Pages) masque toute erreur derrière un 500 modulaire. "
                "→ mettre `optimizeCss: false` (issue #7531).",
            )
        elif not declares_critters(directory):
            problems.append(
                f"{rel} active `experimental.optimizeCss` sans déclarer la dépendance `critters` "
                f"dans {directory.relative_to(root)}/package.json : un `npm ci` propre ne l'installe "
                "pas et le rendu d'erreur Pages répondra 500 (`Cannot find module 'critters'`). "
                "→ déclarer `critters` (ou désactiver l'expérience).",
            )
    return problems


def self_test() -> int:
    """A/B : la garde doit échouer sur l'état du bug et passer sur l'état corrigé."""
    tmp = Path(tempfile.mkdtemp(prefix="next-css-guard-"))
    try:
        app = tmp / "front" / "web"
        app.mkdir(parents=True)
        (app / "package.json").write_text(json.dumps({"name": "web", "dependencies": {}}), encoding="utf-8")
        (app / "next.config.ts").write_text("experimental: {\n  optimizeCss: true,\n},\n", encoding="utf-8")

        # 1) état du bug (App Router, sans critters) → DOIT échouer
        if not check(tmp):
            print("self-test KO : l'état défectueux n'est pas détecté")
            return 1

        # 2) Pages Router + critters déclaré → DOIT passer
        (app / "pages").mkdir()
        (app / "pages" / "index.tsx").write_text("export default function P() { return null }\n", encoding="utf-8")
        (app / "package.json").write_text(
            json.dumps({"name": "web", "dependencies": {"critters": "^0.0.25"}}), encoding="utf-8"
        )
        if check(tmp):
            print("self-test KO : une configuration exécutable est refusée")
            return 1

        # 3) `optimizeCss: false` → DOIT passer, quel que soit le reste
        (app / "next.config.ts").write_text("experimental: {\n  optimizeCss: false,\n},\n", encoding="utf-8")
        if check(tmp):
            print("self-test KO : `optimizeCss: false` est refusé")
            return 1

        print("self-test OK (3 cas : bug détecté, exécutable accepté, désactivé accepté)")
        return 0
    finally:
        shutil.rmtree(tmp, ignore_errors=True)


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("root", nargs="?", default=".")
    parser.add_argument("--self-test", action="store_true")
    args = parser.parse_args()

    if args.self_test:
        return self_test()

    problems = check(Path(args.root).resolve())
    if problems:
        for problem in problems:
            print(f"::error::{problem}")
        print(f"::error::Garde optimizeCss (#7531) : {len(problems)} configuration(s) incohérente(s).")
        return 1

    print("OK — aucune configuration Next n'active `optimizeCss` sans pouvoir l'exécuter (#7531).")
    return 0


if __name__ == "__main__":
    sys.exit(main())
