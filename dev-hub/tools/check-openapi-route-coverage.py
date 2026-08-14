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
  - PASSE INVERSE (issue #2181) : toute opération documentée dans
    openapi.yaml qui n'existe dans AUCUNE route PHP est du drift inverse
    (un client suivant la spec reçoit un 404). Exceptions volontaires dans
    dev-hub/tools/openapi-reverse-allowlist.txt.

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
REVERSE_ALLOWLIST_FILE = Path(__file__).resolve().parent / "openapi-reverse-allowlist.txt"

HTTP_VERBS = {"get", "post", "put", "patch", "delete"}

# Les routes des modules DDD peuvent être enregistrées par leur provider
# (loadRoutesFrom) en dehors de api/routes/ : EdgeSync et SmartAttendance.
MODULE_ROUTE_FILES = [
    REPO_ROOT / "api" / "app" / "Modules" / "EdgeSync" / "routes" / "api.php",
    REPO_ROOT / "api" / "app" / "Modules" / "SmartAttendance" / "routes" / "smart_attendance.php",
]

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

ROUTE_RE = re.compile(
    r"(?:Route::|->)(get|post|put|patch|delete)\(\s*['\"]([^'\"]+)['\"]"
)
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
    files = sorted(ROUTES_DIR.rglob("*.php")) + MODULE_ROUTE_FILES
    for file in files:
        # web.php sert les pages HTML (docs, tester-guide) — hors contrat API.
        if file.name == "web.php" and file.parent == ROUTES_DIR:
            continue
        try:
            rel = file.relative_to(ROUTES_DIR).as_posix()
        except ValueError:
            rel = file.relative_to(REPO_ROOT).as_posix()
        # Les fichiers de routes DDD (enregistrés via loadRoutesFrom) portent
        # leur(s) propre(s) prefix racine(s) `Route::prefix('api/v1/...')`,
        # souvent sur une ligne séparée du groupe : parseur dédié, le dernier
        # prefix vu s'applique au fichier (EdgeSync a plusieurs groupes avec
        # des prefixes différents : api/v1/edge et api/v1/edge-node).
        if file in MODULE_ROUTE_FILES:
            file_prefix = ""
            for raw in file.read_text(encoding="utf-8", errors="replace").splitlines():
                line = raw.strip()
                if not line or line.startswith("//") or line.startswith("*"):
                    continue
                m = PREFIX_RE.search(line)
                if m:
                    file_prefix = m.group(1)
                    continue
                m = ROUTE_RE.search(line)
                if m:
                    verb, path = m.group(1).lower(), m.group(2)
                    routes.append({
                        "method": verb,
                        "path": normalize_path(file_prefix + path),
                        "file": rel,
                    })
                    continue
                m = RESOURCE_RE.search(line)
                if m:
                    kind, name = m.group(1), m.group(2)
                    methods = RESOURCE_METHODS if kind == "resource" else API_RESOURCE_METHODS
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
                        routes.append({
                            "method": verb,
                            "path": normalize_path(file_prefix + p),
                            "file": rel,
                        })
            continue

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


def parse_routes_accurate() -> set[tuple[str, str]]:
    """Ensemble {(method, path)} des routes réelles, préfixes composés
    correctement (séparateur '/'), pour la passe inverse.

    Réutilise le parseur d'audit `scripts/route_openapi_compare.py` (mêmes
    normalisations {param}), en repli sur les formes canoniques du parseur
    principal si le module est indisponible.
    """
    try:
        sys.path.insert(0, str(REPO_ROOT / "scripts"))
        from route_openapi_compare import extract_routes, norm_path

        out: set[tuple[str, str]] = set()
        for verb, path in extract_routes():
            p = norm_path(path).replace("{p}", "{param}")
            for prefix in ("/api/v1", "/api", "/v1"):
                if p.startswith(prefix):
                    p = p[len(prefix):]
                    break
            out.add((verb, p))
        return out
    except Exception:  # pragma: no cover - repli conservateur
        out = set()
        for r in parse_routes():
            out.add((r["method"], r["path"]))
            can = canonical_spec_path(r["path"])
            if can is not None:
                out.add((r["method"], can))
        return out


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


def canonical_spec_path(path: str) -> str | None:
    """Forme canonique d'un chemin pour la comparaison à openapi.yaml.

    Le spec omet le préfixe de version : les `servers` listés portent
    `/api/v1` et les chemins documentés commencent directement par la
    ressource (`/admin/...`, pas `/v1/admin/...`). Le parseur compose donc
    `v1admin/...` (préfixes imbriqués concaténés sans séparateur + version) ;
    cette fonction rétablit la forme documentée (`/admin/...`).

    Gère aussi la forme `api/v1/...` des routes de modules DDD enregistrées
    par provider (EdgeSync, SmartAttendance).

    Renvoie None si le chemin ne commence pas par un préfixe de version
    (les routes sans préfixe `v1` sont comparées telles quelles).
    """
    stripped = path
    m = re.match(r"^api/v\d+", stripped)
    if m:
        stripped = stripped[m.end():]
    else:
        m = re.match(r"^api/", stripped)
        if m:
            stripped = stripped[m.end():]
        else:
            m = re.match(r"^v\d+", stripped)
            if m:
                stripped = stripped[m.end():]
            else:
                return None
    if not stripped.startswith("/"):
        stripped = "/" + stripped
    return stripped


def load_reverse_allowlist() -> set[str]:
    if not REVERSE_ALLOWLIST_FILE.exists():
        return set()
    entries = set()
    for line in REVERSE_ALLOWLIST_FILE.read_text().splitlines():
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
    reverse_allowlist = load_reverse_allowlist()

    # Ensemble des opérations réellement routées, sous toutes leurs formes
    # canoniques (raw + version-stripped) pour la passe inverse.
    route_ops: set[tuple[str, str]] = set()
    for r in routes:
        route_ops.add((r["method"], r["path"]))
        canonical = canonical_spec_path(r["path"])
        if canonical is not None:
            route_ops.add((r["method"], canonical))
    # Passe inverse : parseur précis (préfixes composés avec '/') qui couvre
    # aussi les fichiers de routes DDD (EdgeSync, SmartAttendance).
    route_ops |= parse_routes_accurate()

    # ── Passe inverse : opérations documentées mais non routées (issue #2181) ──
    reverse_drift: list[str] = []
    for method, path in sorted(openapi_ops):
        if (method, path) not in route_ops:
            reverse_drift.append(f"{method.upper()} {path}")
    new_reverse = sorted(set(reverse_drift) - reverse_allowlist)

    uncovered: list[dict] = []
    covered = 0
    for r in routes:
        key = f"{r['method'].upper()} {r['path']}"
        if (r["method"], r["path"]) in openapi_ops:
            covered += 1
            continue
        # Issue #1926 — le spec omet le préfixe de version : un chemin
        # composé `v1admin/rate-validation/pending` est documenté sous
        # `/admin/rate-validation/pending` (voir canonical_spec_path).
        canonical = canonical_spec_path(r["path"])
        if canonical is not None and (r["method"], canonical) in openapi_ops:
            covered += 1
            continue
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
        "reverse_drift": len(reverse_drift),
        "new_reverse_drift": len(new_reverse),
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
        print(f"  - drift inverse (documenté non routé): {len(reverse_drift)} "
              f"(dont {len(new_reverse)} nouveaux)")
        for file, keys in sorted(by_module.items()):
            print(f"  {file}: {len(keys)} non couvertes")

    # Bloquant sur le drift NOUVEAU (non allowlisté) dans les deux sens.
    if new_uncovered:
        print("\n::error::Nouvelles routes sans documentation OpenAPI (issue #1473) :")
        for r in new_uncovered[:50]:
            print(f"::error::  {r['key']}  ({r['file']})")
        return 1
    if new_reverse:
        print("\n::error::Opérations OpenAPI documentées mais absentes des routes PHP (issue #2181) :")
        for key in new_reverse[:50]:
            print(f"::error::  {key}")
        return 1
    return 0


if __name__ == "__main__":
    sys.exit(main())
