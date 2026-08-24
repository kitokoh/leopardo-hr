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
    # ADR-0016 Phase 3 (#5354) : routes géo consolidées sous /attendance/*
    # (chargées par AttendanceServiceProvider via loadRoutesFrom).
    REPO_ROOT / "api" / "app" / "Modules" / "Attendance" / "routes" / "geo.php",
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


def _build_base(prefixes: list[str]) -> str:
    """Joindre les préfixes de groupe avec un séparateur '/' (bug corrigé #2233 :
    la concaténation brute `growth`+`partner`+`/apply` produisait `growthpartner/apply`,
    rendant des centaines de routes impossibles à couvrir par la garde)."""
    parts = [p.strip("/") for p in prefixes if p and p.strip("/")]
    return normalize_path("/" + "/".join(parts)) if parts else ""


def parse_routes() -> list[dict]:
    """Retourne [{method, path, file}] pour chaque route trouvée.

    Corrections #2233 :
      - préfixes imbriqués joints avec '/' (plus de concaténation brute) ;
      - chaînes multi-lignes `->middleware(...)->prefix('x')->group(function` :
        le préfixe est mémorisé et poussé quand le groupe s'ouvre (même sur
        une ligne suivante) ;
      - contexte des fichiers `require`'d depuis routes/api.php : le groupe
        `prefix('v1')` englobant est hérité par chaque module (sinon les
        routes des modules sont incomparables à openapi.yaml).
    """
    routes: list[dict] = []

    def parse_file(file: Path, inherited: list[tuple[int, str]] | None = None) -> None:
        try:
            rel = file.relative_to(ROUTES_DIR).as_posix()
        except ValueError:
            # Fichier hors api/routes/ (modules DDD EdgeSync/SmartAttendance) :
            # étiquette lisible dans le rapport (repo-relative).
            rel = file.relative_to(REPO_ROOT).as_posix()
        # Issue #2489 (régression #2431) : le contexte de préfixe hérité du
        # `require` dans api.php (groupe `v1` englobant) doit amorcer la pile —
        # sinon les routes des modules sont comparées sans le préfixe de
        # version et l'allowlist ne matche plus (drift faux positif massif).
        prefix_stack: list[tuple[int, str]] = list(inherited or [])
        current_prefixes = [p for _, p in prefix_stack]
        pending_prefix: str | None = None

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

            # Issue #2421 : le préfixe peut être sur une ligne SÉPARÉE du
            # ->group(function ...) (pattern multi-lignes) :
            #   Route::middleware([...])
            #       ->prefix('absences')
            #       ->group(function (): void {
            # On mémorise le préfixe « en attente » et on l'applique au groupe
            # ouvert sur la ligne suivante.
            m = PREFIX_RE.search(line)
            if m and "prefix(" in line:
                pending_prefix = m.group(1)

            if GROUP_OPEN_RE.search(line):
                prefix_stack.append((indent, pending_prefix or ""))
                pending_prefix = None
                current_prefixes = [p for _, p in prefix_stack]
                continue
            if m:
                pending_prefix = m.group(1)
                continue

            if GROUP_OPEN_RE.search(line):
                # Groupe sans nouveau préfixe sur la même ligne : hérite du
                # préfixe en attente (ligne précédente) ou du contexte courant.
                prefix_stack.append((indent, pending_prefix if pending_prefix is not None else ""))
                pending_prefix = None
                current_prefixes = [p for _, p in prefix_stack]
                continue

            # Issue #2489 (régression #2431) : jointure avec '/' via
            # _build_base (fix #2233) — la concaténation brute produisait
            # `v1ai/...` au lieu de `v1/ai/...`, incomparable à openapi.yaml.
            base = _build_base(current_prefixes)

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

    # 1) api.php d'abord : collecte des require de modules avec le contexte de
    #    préfixe au point d'inclusion (le groupe `v1` englobant).
    api_file = ROUTES_DIR / "api.php"
    inherited_by_module: dict[str, list[tuple[int, str]]] = {}
    if api_file.exists():
        stack: list[tuple[int, str]] = []
        current: list[str] = []
        pending: str | None = None
        for raw in api_file.read_text(encoding="utf-8", errors="replace").splitlines():
            line = raw.strip()
            if not line or line.startswith("//"):
                continue
            indent = len(raw) - len(raw.lstrip())
            if GROUP_CLOSE_RE.match(raw):
                while stack and stack[-1][0] >= indent:
                    stack.pop()
                current = [p for _, p in stack]
                continue
            m = PREFIX_RE.search(line)
            if m and "prefix(" in line:
                pending = m.group(1)
            if GROUP_OPEN_RE.search(line):
                stack.append((indent, pending or ""))
                pending = None
                current = [p for _, p in stack]
                continue
            m = re.search(r"require\s+__DIR__\.\s*['\"]([^'\"]+)['\"]", line)
            if m and current:
                key = m.group(1).lstrip("./").lstrip("/")
                inherited_by_module.setdefault(key, []).extend(
                    [(i, p) for i, p in stack]
                )

    # 2) Parse de chaque fichier, avec le contexte hérité le cas échéant.
    for file in sorted(ROUTES_DIR.rglob("*.php")):
        # web.php sert les pages HTML (docs, tester-guide) — hors contrat API.
        if file.name == "web.php" and file.parent == ROUTES_DIR:
            continue
        if file == api_file:
            continue  # api.php est analysé à l'étape 1 pour les requires ; ses
            # propres routes restent couvertes par la passe ci-dessous.
        rel = file.relative_to(ROUTES_DIR).as_posix()
        inherited = inherited_by_module.get(rel) or inherited_by_module.get(
            "modules/" + file.name
        )
        parse_file(file, inherited)
    # api.php lui-même (routes directes sous v1, platform, admin, etc.)
    if api_file.exists():
        parse_file(api_file)

    # 3) Routes des modules DDD enregistrées par leur provider via
    #    loadRoutesFrom (hors api/routes/) : EdgeSync, SmartAttendance.
    #    QA 2026-08-15 (#2662) : elles étaient invisibles à la passe directe
    #    de cette garde (seule la passe inverse les couvrait via
    #    scripts/route_openapi_compare.py). Chemins absolus (api/v1/...),
    #    donc aucun préfixe hérité.
    for file in MODULE_ROUTE_FILES:
        if not file.exists():
            print(f"[WARN] Module route file absent: {file}", file=sys.stderr)
            continue
        parse_file(file)

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
            spec = spec_form(p)
            if spec is not None:
                p = spec
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
    m = re.match(r"^/?v\d+(?=/|$)", path)
    if not m:
        return None
    stripped = path[m.end():]
    if not stripped.startswith("/"):
        stripped = "/" + stripped
    return stripped



def spec_form(path: str) -> str | None:
    """Forme spec (hors préfixes version/api) d'un chemin de route réel.

    Les routes de modules DDD enregistrées par provider (EdgeSync,
    SmartAttendance) portent un préfixe absolu `api/v1/...` ; le parseur
    précis les voit sous `v1/api/v1/...` (préfixe `v1` hérité + préfixe
    absolu du fichier). La spec omet TOUS ces préfixes : les `servers`
    portent `/api/v1` et les chemins documentés commencent à la ressource
    (`/edge/health`, pas `/api/v1/edge/health` ni `/v1/api/v1/edge/health`).
    Renvoie None si aucun préfixe connu ne s'applique (comparaison brute).
    """
    for prefix in ("/v1/api/v1", "/api/v1", "/api", "/v1"):
        if path.startswith(prefix):
            stripped = path[len(prefix):]
            return stripped if stripped.startswith("/") else "/" + stripped
    return None


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
    parser.add_argument(
        "--strict-staleness",
        action="store_true",
        help="échoue si une entrée de l'allowlist est en fait documentée "
        "dans openapi.yaml (entrée devenue stale — à purger, issue #3596)",
    )
    args = parser.parse_args()

    routes = parse_routes()
    openapi_ops = parse_openapi()
    allowlist = load_allowlist()
    reverse_allowlist = load_reverse_allowlist()

    # Staleness (issue #3596) : une entrée allowlistée désormais documentée
    # surestime les gaps réels et masque la progression de la remédiation
    # #1473. Comparaison sur les deux formes (brute /v1/x et canonique /x).
    stale_entries: list[str] = []
    for entry in sorted(allowlist):
        parts = entry.split(" ", 1)
        if len(parts) != 2:
            continue
        method, path = parts[0].lower(), parts[1]
        forms = {path}
        canonical = canonical_spec_path(path)
        if canonical is not None:
            forms.add(canonical)
        if any((method, form) in openapi_ops for form in forms):
            stale_entries.append(entry)

    # Ensemble des opérations réellement routées, sous toutes leurs formes
    # canoniques (raw + version-stripped) pour la passe inverse.
    route_ops: set[tuple[str, str]] = set()
    for r in routes:
        route_ops.add((r["method"], r["path"]))
        canonical = canonical_spec_path(r["path"])
        if canonical is not None:
            route_ops.add((r["method"], canonical))
        spec = spec_form(r["path"])
        if spec is not None:
            route_ops.add((r["method"], spec))
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
        # Modules DDD à préfixe absolu (EdgeSync, SmartAttendance) : la spec
        # omet api/v1 (porté par servers) — comparer sous forme spec.
        spec = spec_form(r["path"])
        if spec is not None and (r["method"], spec) in openapi_ops:
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
        "allowlist_stale": len(stale_entries),
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

    if stale_entries:
        print(
            f"\n  - entrées allowlist staleness (désormais documentées): {len(stale_entries)}"
        )
        if args.strict_staleness:
            print(
                "::error::Allowlist OpenAPI stale — entrées à purger (désormais "
                "documentées dans openapi.yaml, issue #3596) :"
            )
            for entry in stale_entries[:50]:
                print(f"::error::  {entry}")
            return 1
    return 0


if __name__ == "__main__":
    sys.exit(main())
