#!/usr/bin/env python3
"""check-admin-destructive-actions.py — garde CI « robustesse des actions admin » (issue #7433).

La console d'admin (front/admin-dashboard, Vue 3) doit respecter deux règles
posées après le retour utilisateur du 2026-09-14 :

1. **Destructif ⇒ confirmation in-app** : jamais `window.confirm`/`window.alert`
   (non i18n, bloque le rendu, non testable) — toujours le composant
   `ConfirmDialog` (ou le composable `useConfirmDialog`).
2. **Jamais d'échec muet** : un bloc `catch` vide ou réduit à un commentaire
   sur un chemin d'action utilisateur avale l'erreur — l'utilisateur croit
   l'action réussie. Les seuls `catch` légitimement muets (stockage local
   indisponible) sont allowlistés explicitement dans
   `dev-hub/tools/admin-silent-catch-allowlist.txt`.

Les commentaires (`//`, `/* */`) sont ignorés lors de l'analyse : citer
`window.confirm` dans un commentaire est légitime.

Usage :
    python3 dev-hub/tools/check-admin-destructive-actions.py [root]

Exit 0 = conforme ; exit 1 = violation (messages ::error:: pour GitHub Actions).
"""

from __future__ import annotations

import re
import sys
from pathlib import Path

SRC_REL = "front/admin-dashboard/src"
ALLOWLIST_REL = "dev-hub/tools/admin-silent-catch-allowlist.txt"

BANNED_RE = re.compile(r"\bwindow\s*\.\s*(confirm|alert)\s*\(")
CATCH_RE = re.compile(r"\bcatch\s*(?:\([^)]*\))?\s*\{")
COMMENT_OR_WS_RE = re.compile(r"(?:\s|//[^\n]*|/\*.*?\*/)*", re.S)


def strip_comments(source: str) -> str:
    """Retire les commentaires ligne/bloc en préservant les retours à la ligne."""
    out: list[str] = []
    i = 0
    n = len(source)
    while i < n:
        two = source[i : i + 2]
        if two == "//":
            j = source.find("\n", i)
            i = n if j == -1 else j
            continue
        if two == "/*":
            j = source.find("*/", i + 2)
            block = source[i : (n if j == -1 else j + 2)]
            out.append("\n" * block.count("\n"))
            i = n if j == -1 else j + 2
            continue
        out.append(source[i])
        i += 1
    return "".join(out)


def line_of(source: str, index: int) -> int:
    return source.count("\n", 0, index) + 1


def load_allowlist(root: Path) -> set[str]:
    path = root / ALLOWLIST_REL
    if not path.is_file():
        return set()
    allowed: set[str] = set()
    for raw in path.read_text(encoding="utf-8").splitlines():
        entry = raw.split("#", 1)[0].strip()
        if entry:
            allowed.add(entry)
    return allowed


def iter_sources(root: Path):
    base = root / SRC_REL
    if not base.is_dir():
        return
    for path in sorted(base.rglob("*")):
        if path.suffix in {".vue", ".js"} and path.is_file():
            yield path


def check(root: Path) -> list[str]:
    allowed = load_allowlist(root)
    errors: list[str] = []

    for path in iter_sources(root):
        rel = path.relative_to(root).as_posix()
        raw = path.read_text(encoding="utf-8")
        code = strip_comments(raw)

        for match in BANNED_RE.finditer(code):
            errors.append(
                f"{rel}:{line_of(code, match.start())} — `window.{match.group(1)}` interdit : "
                "utiliser ConfirmDialog/useConfirmDialog (issue #7433)."
            )

        if rel in allowed:
            continue

        for match in CATCH_RE.finditer(code):
            i = match.end()
            depth = 1
            j = i
            while j < len(code) and depth:
                if code[j] == "{":
                    depth += 1
                elif code[j] == "}":
                    depth -= 1
                j += 1
            body = code[i : j - 1]
            if COMMENT_OR_WS_RE.fullmatch(body):
                errors.append(
                    f"{rel}:{line_of(code, match.start())} — `catch` muet (corps vide ou commentaire "
                    "seul) : afficher l'erreur à l'utilisateur ou allowlister le cas dans "
                    f"{ALLOWLIST_REL} (issue #7433)."
                )

    return errors


def main() -> int:
    root = Path(sys.argv[1]).resolve() if len(sys.argv) > 1 else Path(__file__).resolve().parents[2]
    if not (root / SRC_REL).is_dir():
        print(f"OK — {SRC_REL} absent : garde admin sans objet (fixtures ?).")
        return 0

    errors = check(root)
    if errors:
        for err in errors:
            print(f"::error::Admin {err}", file=sys.stderr)
        print(f"::error::Garde actions admin : {len(errors)} violation(s) (issue #7433).", file=sys.stderr)
        return 1

    print("OK — admin : zéro window.confirm/alert, zéro catch muet non allowlisté (issue #7433).")
    return 0


if __name__ == "__main__":
    sys.exit(main())
