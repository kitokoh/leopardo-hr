#!/usr/bin/env python3
"""check-openapi-route-coverage.py — Couverture OpenAPI des routes Laravel.

Issue #1473 (docs/security/OPENAPI_COVERAGE_GAP_2026-07-19.md) : comparer les
routes déclarées dans api/routes/**/*.php aux opérations documentées dans
api/openapi.yaml, module par module.

Méthode (statique, sans runtime PHP) :
  - parsing des fichiers routes avec résolution des préfixes imbriqués
    (Route::prefix('...')->group(...)) par indentation ;
  - extraction de Route::get/post/put/patch/delete + apiResource/resource ;
  - normalisation des paramètres ({id}, {id?}, {id:regex} → {param}) ;
  - comparaison aux chemins/méthodes de openapi.yaml.

Garde CI (bloquante sur le drift NOUVEAU) :
  - les routes connues comme non documentées vivent dans
    dev-hub/tools/openapi-coverage-allowlist.txt (exclusions documentées) ;
  - toute route absente d'openapi.yaml ET absente de l'allowlist fait échouer
    le job (exit 1) : une nouvelle route doit être documentée, ou ajoutée à
    l'allowlist avec une justification.

Usage :
  python3 dev-hub/tools/check-openapi-route-coverage.py [--json]
"""

from __future__ import annotations

import argparse
import json
import os
import re
import sys
from pathlib import Path

REPO_ROOT = Path(__file__).resolve().parents[2]
ROUTES_DIR = REPO_ROOT / "api" / "routes"
OPENAPI_FILE = REPO_ROOT / "api" / "openapi.yaml"
ALLOWLIST_FILE = Path(__file__).resolve().parent / "openapi-coverage-allowlist.txt"

HTTP_VERBS = {"get", "post", "put", "patch", "delete"}

# Route::apiResource('users') → méthodes CRUD standard Laravel.
RESOURCE_METHODS = {
    "index": "get",
    "create": "get",
    "store": "post",
    "show": "get",
    "edit": "get",
    "update": "put",
    "destroy": "delete",
}
API_RESOURCE_METHODS = {
    "index": "get",
    "store": "post",
    "show": "get",
    "update": "put",
    "destroy": "delete",
}

ROUTE_RE = re.compile(r"Route::(get|post|put|patch|delete)\(\s*['\"]([^'\"]+)['\"]")
PREFIX_RE = re.compile(r"prefix\(\s*['\"]([^'\"]+)['\"]\)")
GROUP_OPEN_RE = re.compile(r"->group\(function")
GROUP_CLOSE_RE = re.compile(r"^\s*\}\);")
RESOURCE_RE = re.compile(
    r"Route::(apiResource|resource)\(\s*['\"]([^'\"]+)['\"]"
)
PARAM_RE = re.compile(r"\{(\w+)(:[^}]+)?(\?)?\}")


def normalize_path(path: str) -> str:
    """{id}, {id?}, {id:regex} → {param} ; supprime le slash final."""
    path = PARAM_RE.sub("{param}", path)
    path = re.sub(r"/{2,}", "/", path)
    return path.rstrip("/") or "/"


def parse_routes() -> list[dict]:
    """Retourne [{method, path, file}] pour chaque route trouvée."""
    routes: list[dict] = []
    for file in sorted(ROUTES_DIR.rglob("*.php")):
        # web.php sert les pages HTML (docs, tester-guide) — hors contrat API.
        if file.name == "web.php" and file.parent == ROUTES_DIR:
            continue
        rel = file.relative_to(ROUTES_DIR).as_posix()
        prefix_stack: list[tuple[int, str]] = []
        current_prefixes: list[str] = []

        for raw in file.read_text(encoding="utf-8", errors="replace").splitlines():
            line = raw.strip()
            if not line or line.startswith("//") or line.startswith("*"):
                continue
            indent = len(raw) - len(raw.lstrip())

            # Fermeture de groupe(s) par indentation.
            if GROUP_CLOSE_RE.match(raw):
                while prefix_stack and prefix_stack[-1][0] >= indent:
                    prefix_stack.pop()
                current_prefixes = [p for _, p in prefix_stack]
                continue

            # Chaîne Route::prefix('...')->...->group(function () { ... });
            # Le préfixe s'applique au groupe ouvert sur cette ligne.
            m = PREFIX_RE.search(line)
            if m and GROUP_OPEN_RE.search(line):
                prefix_stack.append((indent, m.group(1)))
                current_prefixes = [p for _, p in prefix_stack]
                continue

            if GROUP_OPEN_RE.search(line):
                # Groupe sans nouveau préfixe : hérite du contexte courant.
                prefix_stack.append((indent, ""))
                continue

            base = "".join(current_prefixes)

            m = ROUTE_RE.search(line)
            if m:
                verb, path = m.group(1).lower(), m.group(2)
                full = normalize_path(base + path)
                routes.append({"method": verb, "path": full, "file": rel})
                continue

            m = RESOURCE_RE.search(line)
            if m:
                kind, name = m.group(1), m.group(2)
                methods = RESOURCE_METHODS if kind == "resource" else API_RESOURCE_METHODS
                # {param} = singulier du nom de ressource.
                param = re.sub(r"s$", "", name)
                for action, verb in methods.items():
                    if action in ("index", "create"):
                        p = f"/{name}"
                        if action == "create":
                            p = f"/{name}/create"
                    elif action == "store":
                        p = f"/{name}"
                    elif action == "edit":
                        p = f"/{name}/{{{param}}}/edit"
                    elif action in ("show", "update", "destroy"):
                        p = f"/{name}/{{{param}}}"
                    else:
                        continue
                    routes.append(
                        {"method": verb, "path": normalize_path(base + p), "file": rel}
                    )
                continue

        # Pile résiduelle : groupes fermés par fin de fichier (rare).
    return routes


def parse_openapi() -> set[tuple[str, str]]:
    """{(method, normalized_path)} depuis openapi.yaml."""
    text = OPENAPI_FILE.read_text(encoding="utf-8", errors="replace")
    operations: set[tuple[str, str]] = set()
    current_path: str | None = None
    for line in text.splitlines():
        m = re.match(r"^  (/[^:]*):", line)
        if m:
            current_path = m.group(1)
            continue
        if current_path:
            m = re.match(r"^    (get|post|put|patch|delete|options|head):", line)
            if m:
                operations.add((m.group(1), normalize_path(current_path)))
    return operations


def load_allowlist() -> set[str]:
    if not ALLOWLIST_FILE.exists():
        return set()
    entries = set()
    for line in ALLOWLIST_FILE.read_text().splitlines():
        line = line.strip()
        if line and not line.startswith("#"):
            entries.add(line)
    return entries


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--json", action="store_true", help="sortie JSON")
    args = parser.parse_args()

    routes = parse_routes()
    openapi_ops = parse_openapi()
    allowlist = load_allowlist()

    uncovered: list[dict] = []
    covered = 0
    for r in routes:
        key = f"{r['method'].upper()} {r['path']}"
        if (r["method"], r["path"]) in openapi_ops:
            covered += 1
        else:
            r["key"] = key
            uncovered.append(r)

    new_uncovered = [r for r in uncovered if r["key"] not in allowlist]

    by_module: dict[str, list[str]] = {}
    for r in uncovered:
        by_module.setdefault(r["file"], []).append(r["key"])

    report = {
        "routes_total": len(routes),
        "operations_openapi": len(openapi_ops),
        "covered": covered,
        "uncovered": len(uncovered),
        "uncovered_in_allowlist": len(uncovered) - len(new_uncovered),
        "new_uncovered": len(new_uncovered),
        "by_module": {k: v for k, v in sorted(by_module.items())},
    }

    if args.json:
        print(json.dumps(report, indent=2))
    else:
        print(
            f"OpenAPI coverage: {covered}/{len(routes)} routes couvertes "
            f"({len(openapi_ops)} opérations documentées)."
        )
        print(f"  - non couvertes (total): {len(uncovered)}")
        print(f"  - dans l'allowlist (gaps connus): {len(uncovered) - len(new_uncovered)}")
        print(f"  - NOUVELLES non couvertes (drift): {len(new_uncovered)}")
        for file, keys in sorted(by_module.items()):
            print(f"  {file}: {len(keys)} non couvertes")

    # Bloquant uniquement sur le drift NOUVEAU (non allowlisté).
    if new_uncovered:
        print("\n::error::Nouvelles routes sans documentation OpenAPI (issue #1473) :")
        for r in new_uncovered[:50]:
            print(f"::error::  {r['key']}  ({r['file']})")
        return 1
    return 0


if __name__ == "__main__":
    sys.exit(main())
