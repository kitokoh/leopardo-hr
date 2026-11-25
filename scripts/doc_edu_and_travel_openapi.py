#!/usr/bin/env python3
"""Documente les routes EduManager et TravelAgency dans api/openapi.yaml.

Contexte (#eval-2026-11-23) : les verticales `edu_manager` (88 routes) et
`travelagency` (218 routes) etaient entierement absentes du contrat OpenAPI.
Un integrateur generant un client depuis la spec ne pouvait appeler aucune de
ces deux verticales, alors que les routes existent et repondent.

Approche : on ne recopie PAS les routes a la main (302 entrees, derive
garantie). On reutilise le parseur de `dev-hub/tools/check-openapi-route-coverage.py`
— celui-la meme qui sert de garde CI — pour obtenir les couples
(methode, chemin) reellement declares, puis on emet une entree OpenAPI
minimale mais correcte pour chacun :

  - `operationId` stable et unique (`api_<verb>_<path_slug>`) ;
  - `tags` : `EduManager` / `TravelAgency` ;
  - `security` : BearerAuth (sauf les surfaces publiques, marquees `security: []`) ;
  - parametres de chemin typés (`integer` pour les ids numeriques, `string`
    sinon) ;
  - reponses 200/201 + 401/403/404/422 selon le verbe.

Les entrees sont inserees dans `paths:` en respectant l'ordre alphabetique
existant, et les tags sont ajoutes a la section `tags:`.

Usage :
  python3 scripts/doc_edu_and_travel_openapi.py [--check]

`--check` n'ecrit rien et sort en 1 si des entrees manquent.
"""

from __future__ import annotations

import argparse
import re
import sys
from pathlib import Path

REPO_ROOT = Path(__file__).resolve().parents[1]
OPENAPI_FILE = REPO_ROOT / "api" / "openapi.yaml"
TOOLS_DIR = REPO_ROOT / "dev-hub" / "tools"

# Modules a documenter : (fichier de routes, tag OpenAPI, description du tag).
MODULES = [
    (
        "modules/edu_manager.php",
        "EduManager",
        "BC-EDU EDU-MANAGER — verticale scolaire : campus, annees scolaires, "
        "matieres, eleves, classes, admissions, presence, emplois du temps, "
        "evaluations, bulletins, frais scolaires, rapports, RGPD et portail "
        "parents. Toutes les routes sont tenant-scoped et soumises au feature "
        "flag `edumanager` (solution inactive -> 403).",
    ),
    (
        "modules/travelagency.php",
        "TravelAgency",
        "BC-24 TRAVEL — verticale agence de voyage : referentiels (gares, "
        "bureaux, transporteurs, classes, flotte, routes), trajets et tarifs, "
        "reservations et billetterie, locations, fidelite, devises, devis, "
        "hotels et inventaire par date, boutique publique, PDV, corporate, "
        "contenu editorial et webhooks. Tenant-scoped, feature flag "
        "`travelagency`.",
    ),
]

# Surfaces non authentifiees : le token n'est pas un Bearer Sanctum.
PUBLIC_PREFIXES = (
    "/travel/public/",
    "/travel/partner/",
    "/travel/distributor/",
    "/edu-manager/portal/",
    "/edu-manager/guardian-portal/",
)

# Parametres de chemin qui portent un identifiant numerique (bigint).
NUMERIC_PARAM_HINTS = (
    "id",
    "campus",
    "year",
    "subject",
    "student",
    "class",
    "admission",
    "attendance",
    "slot",
    "assessment",
    "grade",
    "card",
    "fee",
    "charge",
    "guardian",
    "import",
    "enrollment",
    "assignment",
    "station",
    "office",
    "carrier",
    "vehicle",
    "route",
    "trip",
    "price",
    "booking",
    "ticket",
    "rental",
    "hotel",
    "room",
    "quiz",
    "question",
    "site",
    "advert",
    "article",
    "comment",
    "category",
    "contact",
    "payment",
    "export",
    "subscription",
    "account",
    "quote",
    "key",
    "rate",
    "policy",
    "assignment",
    "batch",
    "kind",
    "param",
)


def load_parser():
    """Importe le parseur de la garde CI (source unique de verite)."""
    sys.path.insert(0, str(TOOLS_DIR))
    import importlib

    mod = importlib.import_module("check-openapi-route-coverage")
    return mod


def spec_path(raw_path: str) -> str:
    """`/v1/edu-manager/campuses` -> `/edu-manager/campuses` (la spec omet /v1)."""
    for prefix in ("/v1/api/v1", "/api/v1", "/api", "/v1"):
        if raw_path.startswith(prefix):
            stripped = raw_path[len(prefix):]
            return stripped if stripped.startswith("/") else "/" + stripped
    return raw_path


def slugify(path: str) -> str:
    """`/edu-manager/classes/{param}/attendances` -> `edu_manager_classes_param_attendances`."""
    s = path.strip("/").replace("{param}", "param")
    s = re.sub(r"[^a-zA-Z0-9]+", "_", s)
    return s.strip("_").lower()


def is_public(path: str) -> bool:
    return any(path.startswith(p) for p in PUBLIC_PREFIXES)


def param_schema(name: str) -> str:
    """Type OpenAPI d'un parametre de chemin."""
    lowered = name.lower()
    if lowered in NUMERIC_PARAM_HINTS or lowered.endswith("_id"):
        return "integer"
    return "string"


def render_operation(verb: str, path: str, tag: str, params: list[str]) -> str:
    """Emet le bloc YAML d'une operation (indente pour `paths:`).

    `params` porte les NOMS REELS des parametres de chemin (`campus`,
    `travelTrip`, ...) : la garde CI compare les noms, pas seulement le
    nombre. Emettre `{param}` partout produisait 190 faux drifts.
    """
    op_id = f"api_{verb}_{slugify(path)}"
    lines: list[str] = []
    lines.append(f"    {verb}:")
    lines.append(f"      operationId: {op_id}")
    lines.append(f"      tags: [{tag}]")
    lines.append(f"      summary: {verb.upper()} {path}")
    lines.append("      description: |")
    lines.append(
        "        Route de la verticale documentee par #eval-2026-11-23. "
        "Tenant-scoped : le `company_id` est resolu depuis le token, jamais "
        "depuis la requete. Un identifiant appartenant a un autre tenant "
        "repond 404 (jamais 403, pour ne pas divulguer son existence)."
    )

    if is_public(path):
        lines.append("      security: []")
    else:
        lines.append("      security:")
        lines.append("      - BearerAuth: []")

    if params:
        lines.append("      parameters:")
        for name in params:
            lines.append(f"      - name: {name}")
            lines.append("        in: path")
            lines.append("        required: true")
            lines.append("        schema:")
            lines.append(f"          type: {param_schema(name)}")

    lines.append("      responses:")
    if verb == "post":
        lines.append("        '201':")
        lines.append("          description: Ressource creee")
    else:
        lines.append("        '200':")
        lines.append("          description: Succes")

    if not is_public(path):
        lines.append("        '401':")
        lines.append("          $ref: '#/components/responses/Error401'")
        lines.append("        '403':")
        lines.append("          $ref: '#/components/responses/Error403'")

    if params:
        lines.append("        '404':")
        lines.append("          $ref: '#/components/responses/Error404'")

    if verb in ("post", "put", "patch"):
        lines.append("        '422':")
        lines.append("          $ref: '#/components/responses/Error422'")

    return "\n".join(lines)


def named_path(normalized: str, params: list[str]) -> str:
    """Reinjecte les noms reels dans un chemin normalise.

    Le parseur normalise `{campus}` -> `{param}` ; la garde CI compare
    ensuite les NOMS documentes a ceux du code. On remet donc les noms
    reels, dans l'ordre, pour que la cle de chemin porte `{campus}` et non
    `{param}`.
    """
    out = normalized
    for name in params:
        out = out.replace("{param}", "{" + name + "}", 1)
    return out


def collect_routes(parser) -> dict[str, dict[str, dict]]:
    """{tag: {spec_path: {"verbs": [...], "params": [...]}}} pour les modules cibles."""
    wanted = {f"modules/{Path(f).name}": tag for f, tag, _ in MODULES}
    out: dict[str, dict[str, dict]] = {tag: {} for _, tag, _ in MODULES}

    for route in parser.parse_routes():
        tag = wanted.get(route["file"])
        if tag is None:
            continue
        params = list(route.get("params") or [])
        path = named_path(spec_path(route["path"]), params)
        entry = out[tag].setdefault(path, {"verbs": [], "params": params})
        if route["method"] not in entry["verbs"]:
            entry["verbs"].append(route["method"])

    return out


def existing_operations(text: str) -> set[tuple[str, str]]:
    ops: set[tuple[str, str]] = set()
    current: str | None = None
    for line in text.splitlines():
        m = re.match(r"^  (/[^:]*):", line)
        if m:
            current = m.group(1)
            continue
        if current:
            m = re.match(r"^    (get|post|put|patch|delete|options|head):", line)
            if m:
                ops.add((m.group(1), current))
    return ops


def insert_paths(text: str, blocks: dict[str, list[str]]) -> str:
    """Insere les operations manquantes dans la section `paths:`.

    `blocks` = {chemin: [lignes d'operation]}. Un chemin DEJA documente
    recoit les verbes manquants a la fin de son entree (le spec peut
    documenter `GET /x` sans `POST /x` : ignorer le chemin entier laissait
    la route non couverte). Un chemin absent est ajoute en ordre alphabetique.
    """
    lines = text.splitlines()
    try:
        start = lines.index("paths:")
    except ValueError:
        raise SystemExit("Section `paths:` introuvable dans openapi.yaml")

    # Fin de la section paths : premiere ligne de niveau 0 apres `paths:`.
    end = len(lines)
    for i in range(start + 1, len(lines)):
        if lines[i] and not lines[i].startswith(" "):
            end = i
            break

    body = lines[start + 1:end]

    # Index des chemins existants (ligne de niveau 2 commencant par "  /").
    entries: list[tuple[str, list[str]]] = []
    current_path: str | None = None
    current_lines: list[str] = []
    for line in body:
        m = re.match(r"^  (/[^:]*):", line)
        if m:
            if current_path is not None:
                entries.append((current_path, current_lines))
            current_path = m.group(1)
            current_lines = [line]
        elif current_path is not None:
            current_lines.append(line)
        else:
            # Lignes hors entree (commentaires de section) : conservees telles quelles.
            entries.append(("", [line]))
    if current_path is not None:
        entries.append((current_path, current_lines))

    known = {p for p, _ in entries if p}
    for path, op_lines in blocks.items():
        if path in known:
            # Fusion : on complete l'entree existante avec les verbes manquants.
            for idx, (existing_path, chunk) in enumerate(entries):
                if existing_path == path:
                    entries[idx] = (existing_path, chunk + op_lines)
                    break
        else:
            entries.append((path, [f"  {path}:"] + op_lines))

    # Tri stable : les entrees sans chemin (commentaires) restent en tete.
    head = [e for e in entries if not e[0]]
    tail = sorted([e for e in entries if e[0]], key=lambda e: e[0])

    new_body: list[str] = []
    for _, chunk in head + tail:
        new_body.extend(chunk)

    return "\n".join(lines[: start + 1] + new_body + lines[end:]) + "\n"


def ensure_tags(text: str) -> str:
    """Ajoute les tags manquants a la section `tags:`."""
    lines = text.splitlines()
    try:
        start = lines.index("tags:")
    except ValueError:
        raise SystemExit("Section `tags:` introuvable dans openapi.yaml")

    end = len(lines)
    for i in range(start + 1, len(lines)):
        if lines[i] and not lines[i].startswith(" "):
            end = i
            break

    existing = set()
    for line in lines[start + 1:end]:
        m = re.match(r"^- name: (.+)$", line)
        if m:
            existing.add(m.group(1).strip())

    additions: list[str] = []
    for _, tag, description in MODULES:
        if tag in existing:
            continue
        additions.append(f"- name: {tag}")
        additions.append(f"  description: '{description}'")

    if not additions:
        return text

    return "\n".join(lines[:end] + additions + lines[end:]) + "\n"


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--check", action="store_true", help="ne rien ecrire, sortir 1 si incomplet")
    args = ap.parse_args()

    parser = load_parser()
    routes = collect_routes(parser)

    text = OPENAPI_FILE.read_text(encoding="utf-8")
    ops = existing_operations(text)

    blocks: dict[str, list[str]] = {}
    missing = 0
    for tag, paths in routes.items():
        for path, entry in sorted(paths.items()):
            todo = [v for v in entry["verbs"] if (v, path) not in ops]
            if not todo:
                continue
            missing += len(todo)
            blocks[path] = [
                render_operation(v, path, tag, entry["params"]) for v in sorted(todo)
            ]

    total = sum(len(e["verbs"]) for paths in routes.values() for e in paths.values())
    print(f"routes cibles : {total}")
    print(f"operations manquantes : {missing}")

    if args.check:
        return 1 if missing else 0

    if not blocks:
        print("Rien a inserer.")
        return 0

    text = insert_paths(text, blocks)
    text = ensure_tags(text)
    OPENAPI_FILE.write_text(text, encoding="utf-8")
    print(f"{len(blocks)} chemins inseres dans {OPENAPI_FILE.relative_to(REPO_ROOT)}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
