#!/usr/bin/env python3
"""Documente les routes CRM et RestaurantManager dans api/openapi.yaml.

Contexte (#eval-2026-11-24) : apres la vague edu_manager/travelagency, il
restait 62 routes `modules/crm.php` et 155 routes
`modules/restaurantmanager.php` non couvertes par le contrat OpenAPI. Elles
etaient gelees dans `dev-hub/tools/openapi-coverage-allowlist.txt` : un
integrateur generant un client depuis la spec ne pouvait appeler ni le CRM
(canaux, automations, exports, segments, pipelines) ni la verticale
restaurant (POS, cuisine, caisse, stock, reservations, impression).

Approche identique a `doc_edu_and_travel_openapi.py` : on ne recopie PAS les
routes a la main, on reutilise le parseur de la garde CI
(`dev-hub/tools/check-openapi-route-coverage.py`) — source unique de verite —
puis on emet une operation OpenAPI correcte pour chaque couple
(methode, chemin) manquant.

Specificites traitees ici, absentes du generateur edu/travel :

  - **Surfaces publiques** : `/public/restaurants/*` (annuaire, commande en
    ligne, avis), `/restaurant/public/*` (menu, commande, paiement signe),
    `/restaurant/marketplace/{provider}/webhooks` et
    `/crm/webhooks/whatsapp`. Elles n'utilisent PAS Sanctum : `security: []`.
  - **Bridge d'impression** : `/restaurant/printing/bridge/*` s'authentifie
    par un jeton d'appareil partage (`X-Restaurant-Print-Bridge-Token` +
    `X-Company-Id`), pas par un Bearer. On declare ce scheme plutot que de
    mentir avec BearerAuth.
  - **Parametres de requete** : `GET /restaurant/printing/bridge/jobs` exige
    `branch_id` en query (le controleur lit `$request->query('branch_id')` et
    repond 422 sans lui). C'est le point qui avait produit un drift de
    parametre en recette v3 ; il est declare ici.

Usage :
  python3 scripts/doc_crm_and_restaurant_openapi.py [--check]

`--check` n'ecrit rien et sort en 1 si des operations manquent.
"""

from __future__ import annotations

import argparse
import re
import sys
from pathlib import Path

REPO_ROOT = Path(__file__).resolve().parents[1]
OPENAPI_FILE = REPO_ROOT / "api" / "openapi.yaml"
TOOLS_DIR = REPO_ROOT / "dev-hub" / "tools"

# (fichier de routes, tag OpenAPI, description du tag)
MODULES = [
    (
        "modules/crm.php",
        "CRM",
        "BC-CRM — gestion de la relation client : comptes, contacts, "
        "opportunites, pipelines, activites, segments, canaux de messagerie "
        "(WhatsApp, e-mail, SMS), automations et exports. Tenant-scoped, "
        "feature flag `crm`.",
    ),
    (
        "modules/restaurantmanager.php",
        "RestaurantManager",
        "BC-RESTO — verticale restauration : succursales, zones, tables, "
        "produits et menus, commandes (sur place, a emporter, livraison), "
        "cuisine, caisse et sessions, paiements, stock, reservations, "
        "personnel, avis, impression et surfaces publiques. Tenant-scoped, "
        "feature flag `restaurantmanager`.",
    ),
]

# Surfaces SANS Sanctum : ni Bearer, ni tenant middleware.
PUBLIC_PREFIXES = (
    "/public/restaurants",
    "/restaurant/public/",
    "/restaurant/marketplace/",
    "/crm/webhooks/",
)

# Bridge d'impression : jeton d'appareil partage, pas un Bearer utilisateur.
BRIDGE_PREFIX = "/restaurant/printing/bridge"

# Parametres de requete OBLIGATOIRES, par (methode, chemin).
# `GET /restaurant/printing/bridge/jobs` lit `$request->query('branch_id')`
# et renvoie 422 sans lui : le declarer evite le drift spec<->code.
REQUIRED_QUERY_PARAMS: dict[tuple[str, str], list[tuple[str, str, bool]]] = {
    ("get", "/restaurant/printing/bridge/jobs"): [
        ("branch_id", "integer", True),
        ("status", "string", False),
        ("order_id", "integer", False),
    ],
}

NUMERIC_PARAM_HINTS = (
    "id",
    "branch",
    "zone",
    "table",
    "product",
    "category",
    "menu",
    "order",
    "item",
    "payment",
    "session",
    "shift",
    "stock",
    "movement",
    "supplier",
    "ingredient",
    "recipe",
    "reservation",
    "review",
    "staff",
    "job",
    "channel",
    "automation",
    "export",
    "segment",
    "pipeline",
    "stage",
    "account",
    "contact",
    "opportunity",
    "activity",
    "event",
    "run",
    "param",
)


def load_parser():
    """Importe le parseur de la garde CI (source unique de verite)."""
    sys.path.insert(0, str(TOOLS_DIR))
    import importlib

    return importlib.import_module("check-openapi-route-coverage")


def spec_path(raw_path: str) -> str:
    """`/v1/crm/channels` -> `/crm/channels` (la spec omet /v1)."""
    for prefix in ("/v1/api/v1", "/api/v1", "/api", "/v1"):
        if raw_path.startswith(prefix):
            stripped = raw_path[len(prefix):]
            return stripped if stripped.startswith("/") else "/" + stripped
    return raw_path


def slugify(path: str) -> str:
    s = path.strip("/").replace("{param}", "param")
    s = re.sub(r"[^a-zA-Z0-9]+", "_", s)
    return s.strip("_").lower()


def is_public(path: str) -> bool:
    return any(path.startswith(p) for p in PUBLIC_PREFIXES)


def is_bridge(path: str) -> bool:
    return path.startswith(BRIDGE_PREFIX)


def param_schema(name: str) -> str:
    lowered = name.lower()
    if lowered in NUMERIC_PARAM_HINTS or lowered.endswith("_id"):
        return "integer"
    return "string"


def render_operation(verb: str, path: str, tag: str, params: list[str]) -> str:
    """Emet le bloc YAML d'une operation (indente pour `paths:`)."""
    op_id = f"api_{verb}_{slugify(path)}"
    lines: list[str] = []
    lines.append(f"    {verb}:")
    lines.append(f"      operationId: {op_id}")
    lines.append(f"      tags: [{tag}]")
    lines.append(f"      summary: {verb.upper()} {path}")

    if is_public(path):
        lines.append(
            "      description: |\n"
            "        Surface PUBLIQUE : aucun token Sanctum. Le tenant est resolu "
            "par le slug, le lien signe ou le jeton de boutique ; 404 fail-closed "
            "si la ressource n'est pas publiee."
        )
        lines.append("      security: []")
    elif is_bridge(path):
        lines.append(
            "      description: |\n"
            "        Bridge d'impression : authentifie par un JETON D'APPAREIL "
            "partage (`X-Restaurant-Print-Bridge-Token`) et `X-Company-Id`, "
            "jamais par un Bearer utilisateur. Fail-closed si le jeton est "
            "absent ou invalide."
        )
        lines.append("      security:")
        lines.append("      - RestaurantPrintBridge: []")
    else:
        lines.append(
            "      description: |\n"
            "        Tenant-scoped : le `company_id` est resolu depuis le token, "
            "jamais depuis la requete. Un identifiant appartenant a un autre "
            "tenant repond 404 (jamais 403, pour ne pas divulguer son existence)."
        )
        lines.append("      security:")
        lines.append("      - BearerAuth: []")

    query_params = REQUIRED_QUERY_PARAMS.get((verb, path), [])

    if params or query_params:
        lines.append("      parameters:")
        for name in params:
            lines.append(f"      - name: {name}")
            lines.append("        in: path")
            lines.append("        required: true")
            lines.append("        schema:")
            lines.append(f"          type: {param_schema(name)}")
        for name, ptype, required in query_params:
            lines.append(f"      - name: {name}")
            lines.append("        in: query")
            lines.append(f"        required: {'true' if required else 'false'}")
            lines.append("        schema:")
            lines.append(f"          type: {ptype}")

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
    else:
        # Surfaces publiques : pas de 401/403 (aucun token), mais Redocly
        # exige au moins une reponse 4xx. Le vrai 4xx d'une surface publique
        # est le 404 fail-closed (slug inconnu, branche non publiee) et le
        # 422 d'une commande invalide — on les declare plutot que d'inventer
        # un 401 qui n'arrive jamais.
        lines.append("        '404':")
        lines.append("          $ref: '#/components/responses/Error404'")

    if params and not is_public(path):
        lines.append("        '404':")
        lines.append("          $ref: '#/components/responses/Error404'")

    if verb in ("post", "put", "patch"):
        lines.append("        '422':")
        lines.append("          $ref: '#/components/responses/Error422'")

    return "\n".join(lines)


def named_path(normalized: str, params: list[str]) -> str:
    """Reinjecte les noms reels des parametres dans un chemin normalise."""
    out = normalized
    for name in params:
        out = out.replace("{param}", "{" + name + "}", 1)
    return out


def collect_routes(parser) -> dict[str, dict[str, dict]]:
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
    """Insere les operations manquantes dans la section `paths:`."""
    lines = text.splitlines()
    try:
        start = lines.index("paths:")
    except ValueError:
        raise SystemExit("Section `paths:` introuvable dans openapi.yaml")

    end = len(lines)
    for i in range(start + 1, len(lines)):
        if lines[i] and not lines[i].startswith(" "):
            end = i
            break

    body = lines[start + 1:end]

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
            entries.append(("", [line]))
    if current_path is not None:
        entries.append((current_path, current_lines))

    known = {p for p, _ in entries if p}
    for path, op_lines in blocks.items():
        if path in known:
            for idx, (existing_path, chunk) in enumerate(entries):
                if existing_path == path:
                    entries[idx] = (existing_path, chunk + op_lines)
                    break
        else:
            entries.append((path, [f"  {path}:"] + op_lines))

    head = [e for e in entries if not e[0]]
    tail = sorted([e for e in entries if e[0]], key=lambda e: e[0])

    new_body: list[str] = []
    for _, chunk in head + tail:
        new_body.extend(chunk)

    return "\n".join(lines[: start + 1] + new_body + lines[end:]) + "\n"


def ensure_tags(text: str) -> str:
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


def ensure_bridge_security_scheme(text: str) -> str:
    """Declare le scheme `RestaurantPrintBridge` s'il manque.

    Le bridge n'utilise pas Sanctum : sans ce scheme, les operations
    referencent un nom absent de `components.securitySchemes` et Redocly
    signale une reference non resolue.
    """
    if "RestaurantPrintBridge:" in text:
        return text

    lines = text.splitlines()
    try:
        start = lines.index("  securitySchemes:")
    except ValueError:
        raise SystemExit("Section `components.securitySchemes:` introuvable")

    # Fin du bloc securitySchemes : premiere ligne a 2 espaces ou moins.
    end = len(lines)
    for i in range(start + 1, len(lines)):
        if lines[i].strip() and not lines[i].startswith("    "):
            end = i
            break

    block = [
        "    RestaurantPrintBridge:",
        "      type: apiKey",
        "      in: header",
        "      name: X-Restaurant-Print-Bridge-Token",
        "      description: |",
        "        Jeton d'appareil partage, enrole par un manager de la succursale.",
        "        Le bridge envoie aussi `X-Company-Id` pour resoudre le tenant.",
        "        Ce n'est PAS un Bearer utilisateur : un agent d'impression n'a",
        "        aucun compte et ne peut appeler aucune autre route.",
    ]

    return "\n".join(lines[:end] + block + lines[end:]) + "\n"


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
    text = ensure_bridge_security_scheme(text)
    OPENAPI_FILE.write_text(text, encoding="utf-8")
    print(f"{len(blocks)} chemins inseres dans {OPENAPI_FILE.relative_to(REPO_ROOT)}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
