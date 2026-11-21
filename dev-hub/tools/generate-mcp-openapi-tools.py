#!/usr/bin/env python3
"""generate-mcp-openapi-tools.py — Outils MCP CRUD générés depuis api/openapi.yaml.

Issue #9746 (EPIC #9739, BC-23 AI).

Le serveur MCP (`dev-hub/mcp-server`) expose deux sources d'outils :

  1. le contrat A3 (`AIToolDefinitionRegistry` → `manifest/tools.json`), écrit à
     la main par les bounded contexts propriétaires ;
  2. la surface CRUD de l'API, GÉNÉRÉE ici depuis `api/openapi.yaml` →
     `manifest/openapi-tools.json`.

Pourquoi générer : `api/openapi.yaml` documente ~1 950 opérations. Écrire à la
main les définitions MCP correspondantes garantirait une dérive silencieuse dès
la première route ajoutée. Le générateur rend la dérive BRUYANTE (`--check`).

Périmètre volontairement étroit :

  - **allowlist de tags** : seuls les modules RH/plateforme listés ci-dessous
    sont exposés (pas les 1 950 opérations : un client MCP n'a pas à voir la
    surface Fuel/Travel/Restaurant) ;
  - **verbe CRUD uniquement** : les endpoints d'ACTION (`/approve`, `/lock`,
    `/confirm`, `/publish`…) sont EXCLUS par défaut — ils passent par le flux
    `PendingAction` de Laravel, jamais par une exécution directe. L'option
    `--include-actions` (issue #9753) les génère comme outils de PROPOSITION :
    `tools/call` crée alors une action en attente au lieu d'exécuter ;
  - **`/public/*` et `/webhooks/*` exclus** : pas d'authentification tenant,
    donc hors du modèle d'autorisation du serveur MCP.

Sensibilité (jamais décidée par le serveur MCP, seulement dérivée du verbe) :

  - `GET`                        → `read`  (exécution directe, RBAC côté Laravel)
  - `POST`/`PUT`/`PATCH`/`DELETE` → `write` (refusée en exécution directe)

Usage :
  python3 dev-hub/tools/generate-mcp-openapi-tools.py            # écrit le fichier
  python3 dev-hub/tools/generate-mcp-openapi-tools.py --check    # garde CI (exit 1 si dérive)
  python3 dev-hub/tools/generate-mcp-openapi-tools.py --stdout   # affiche sans écrire
  python3 dev-hub/tools/generate-mcp-openapi-tools.py --include-actions  # + outils d'action (#9753)
"""

from __future__ import annotations

import argparse
import json
import re
import sys
from pathlib import Path
from typing import Any

try:
    import yaml
except ImportError:  # pragma: no cover - dépendance déclarée dans le workflow CI
    sys.stderr.write(
        "PyYAML requis : python3 -m pip install --user pyyaml\n"
    )
    raise SystemExit(2)

REPO_ROOT = Path(__file__).resolve().parents[2]
SPEC_PATH = REPO_ROOT / "api" / "openapi.yaml"
OUTPUT_PATH = REPO_ROOT / "dev-hub" / "mcp-server" / "manifest" / "openapi-tools.json"

# Préfixe réel des routes (le `servers[0].url` de la spec se termine par /api/v1).
API_PREFIX = "/api/v1"

# Tags exposés. Volontairement restreint : la surface MCP est un sous-ensemble
# stable et relisible, pas un miroir des 1 950 opérations.
#
# Issue #9751 — ajout des verticales Travel et Restaurant. Ce n'est pas un
# élargissement « pour être complet » : ce sont deux verticales métier réelles
# (agence de voyage, restaurateur pilote) qui portent des cas d'usage concrets
# pour un client MCP externe. Les autres verticales (FuelStation, Retail,
# HealthManager, Pharmacy…) restent hors périmètre tant qu'aucun consommateur
# externe ne les demande — exposer 1 950 opérations rendrait `tools/list`
# illisible pour un agent et saturerait son contexte.
ALLOWED_TAGS = {
    "Employees",
    "Tasks",
    "Projects",
    "Schedules",
    "Attendance",
    "Evaluations",
    "Payroll",
    "Me",
    "Notifications",
    "HR Referentials",
    # Issue #9751 — verticales Travel et Restaurant.
    "Travel",
    "Restaurant",
}

# Dernier segment d'un chemin qui dénote une ACTION métier (et non du CRUD).
# Ces endpoints ne sont jamais exposés : ils exigent une confirmation humaine
# via le flux PendingAction (spec §3 « jamais de confiance au LLM »).
ACTION_SEGMENTS = {
    "approve", "reject", "lock", "unlock", "confirm", "publish", "pause",
    "close", "cancel", "pay", "validate", "renew", "rotate", "process",
    "mark-paid", "fail", "send", "assign-employees", "submit", "acknowledge",
    "archive", "departure", "scan-employee", "create-employee", "activate",
    "export", "import", "sync", "generate", "preview", "estimate", "classify",
    "reply-draft", "contribute", "support", "restore", "duplicate", "merge",
    "transfer", "reset", "revoke", "regenerate", "download", "upload",
}

HTTP_METHODS = ("get", "post", "put", "patch", "delete")

NAME_RE = re.compile(r"^[a-z][a-z0-9_]{1,63}$")


# --------------------------------------------------------------------------
# Résolution des $ref
# --------------------------------------------------------------------------

def resolve_ref(spec: dict[str, Any], ref: str) -> Any:
    """Résout un pointeur local `#/components/...` (aucune référence externe)."""
    if not ref.startswith("#/"):
        return None
    node: Any = spec
    for part in ref[2:].split("/"):
        part = part.replace("~1", "/").replace("~0", "~")
        if not isinstance(node, dict) or part not in node:
            return None
        node = node[part]
    return node


def deref(spec: dict[str, Any], node: Any, depth: int = 0) -> Any:
    """Déplie un `$ref` (profondeur bornée pour éviter les cycles)."""
    if depth > 8 or not isinstance(node, dict):
        return node
    ref = node.get("$ref")
    if isinstance(ref, str):
        target = resolve_ref(spec, ref)
        if target is None:
            return {}
        return deref(spec, target, depth + 1)
    return node


def ref_name(node: Any) -> str | None:
    if isinstance(node, dict) and isinstance(node.get("$ref"), str):
        return node["$ref"].rsplit("/", 1)[-1]
    return None


def shallow_schema(spec: dict[str, Any], schema: Any, depth: int = 0) -> dict[str, Any]:
    """Schéma JSON exploitable par un client MCP, sans explosion combinatoire.

    On déplie les `$ref` de premier niveau (pour exposer les vrais noms de
    champs d'un corps de requête) mais on remplace les objets imbriqués par un
    descripteur lisible plutôt que de les inliner récursivement : un schéma de
    4 000 lignes n'aide aucun agent, il sature son contexte.
    """
    if not isinstance(schema, dict):
        return {"type": "object"}

    if depth > 3:
        name = ref_name(schema)
        return {"type": "object", "description": f"Objet {name} (voir api/openapi.yaml)." if name else "Objet imbriqué."}

    name = ref_name(schema)
    if name is not None:
        target = resolve_ref(spec, schema["$ref"])
        if target is None:
            return {"type": "object", "description": f"Objet {name} (voir api/openapi.yaml)."}
        resolved = shallow_schema(spec, target, depth + 1)
        if isinstance(resolved, dict) and "description" not in resolved:
            resolved = {**resolved, "description": f"Schéma {name} (api/openapi.yaml)."}
        return resolved

    out: dict[str, Any] = {}
    for key in ("type", "format", "enum", "minimum", "maximum", "minLength", "maxLength", "pattern", "default", "nullable"):
        if key in schema:
            out[key] = schema[key]

    if "description" in schema:
        out["description"] = schema["description"]

    if isinstance(schema.get("items"), dict):
        out["items"] = shallow_schema(spec, schema["items"], depth + 1)

    if isinstance(schema.get("properties"), dict):
        out["type"] = out.get("type", "object")
        out["properties"] = {
            key: shallow_schema(spec, value, depth + 1)
            for key, value in sorted(schema["properties"].items())
        }
        if isinstance(schema.get("required"), list):
            out["required"] = sorted(str(item) for item in schema["required"])

    if not out:
        out["type"] = "object"

    return out


# --------------------------------------------------------------------------
# Extraction des opérations
# --------------------------------------------------------------------------

def normalize_path(path: str) -> str:
    """`/v1/audit-logs` et `/tasks` désignent tous deux `/api/v1/...`."""
    if path.startswith("/v1/"):
        return API_PREFIX + path[len("/v1"):]
    if path == "/v1":
        return API_PREFIX
    return API_PREFIX + path


def is_action_path(path: str) -> bool:
    segments = [segment for segment in path.split("/") if segment]
    if not segments:
        return False
    last = segments[-1]
    if last.startswith("{"):
        return False
    return last in ACTION_SEGMENTS


def is_excluded_path(path: str) -> bool:
    return path.startswith("/public/") or path.startswith("/webhooks/") or "/public/" in path


def collect_parameters(spec: dict[str, Any], operation: dict[str, Any], path_item: dict[str, Any]) -> list[dict[str, Any]]:
    """Paramètres de l'opération + ceux du path item, déréférencés."""
    merged: list[dict[str, Any]] = []
    seen: set[tuple[str, str]] = set()

    for source in (path_item.get("parameters") or [], operation.get("parameters") or []):
        for raw in source:
            param = deref(spec, raw)
            if not isinstance(param, dict):
                continue
            name = param.get("name")
            location = param.get("in")
            if not isinstance(name, str) or not isinstance(location, str):
                continue
            key = (name, location)
            if key in seen:
                continue
            seen.add(key)
            merged.append(param)

    return merged


def sanitize_name(operation_id: str, method: str, path: str) -> str:
    candidate = re.sub(r"[^a-z0-9_]+", "_", operation_id.lower()).strip("_")
    if not candidate:
        candidate = re.sub(r"[^a-z0-9_]+", "_", f"{method}_{path}".lower()).strip("_")
    if not candidate or not candidate[0].isalpha():
        candidate = f"api_{candidate}".strip("_")
    if len(candidate) > 63:
        # Troncature déterministe : on garde un suffixe de hachage court pour
        # qu'aucun outil ne collisionne après troncature.
        import hashlib

        digest = hashlib.sha1(candidate.encode("utf-8")).hexdigest()[:8]
        candidate = f"{candidate[:54]}_{digest}"
    if not NAME_RE.match(candidate):
        raise ValueError(f"Nom d'outil non conforme : {candidate!r}")
    return candidate


def build_tool(
    spec: dict[str, Any],
    method: str,
    path: str,
    operation: dict[str, Any],
    path_item: dict[str, Any],
    is_action: bool = False,
) -> dict[str, Any]:
    operation_id = operation.get("operationId") or f"{method}_{path}"
    name = sanitize_name(str(operation_id), method, path)
    full_path = normalize_path(path)

    parameters = collect_parameters(spec, operation, path_item)

    properties: dict[str, Any] = {}
    required: list[str] = []
    path_params: list[str] = []
    query_params: list[str] = []

    for param in parameters:
        pname = str(param["name"])
        location = str(param["in"])
        if location not in ("path", "query"):
            continue
        schema = shallow_schema(spec, param.get("schema") or {})
        if isinstance(param.get("description"), str) and "description" not in schema:
            schema = {**schema, "description": param["description"]}
        properties[pname] = schema
        if location == "path":
            path_params.append(pname)
            required.append(pname)
        else:
            query_params.append(pname)
            if param.get("required") is True:
                required.append(pname)

    request_body = deref(spec, operation.get("requestBody") or {})
    has_body = False
    if isinstance(request_body, dict) and request_body:
        content = request_body.get("content") or {}
        media = content.get("application/json") or next(iter(content.values()), {})
        body_schema = shallow_schema(spec, (media or {}).get("schema") or {})
        properties["body"] = body_schema
        has_body = True
        if request_body.get("required") is True:
            required.append("body")

    input_schema: dict[str, Any] = {
        "type": "object",
        "properties": dict(sorted(properties.items())),
    }
    if required:
        input_schema["required"] = sorted(set(required))
    if not properties:
        input_schema["properties"] = {}

    summary = str(operation.get("summary") or operation_id).strip()
    sensitivity = "read" if method == "get" else "write"

    if is_action:
        # Issue #9753 — endpoint d'ACTION : jamais exécuté directement. Le
        # serveur MCP crée une proposition (`POST /api/v1/ai/actions/propose`)
        # et un humain confirme. La sensibilité reste `write` : une action
        # n'est jamais une lecture, même quand le verbe HTTP est GET
        # (`/departure`, `/preview`).
        sensitivity = "write"
        verb = "Action métier — PROPOSITION uniquement, confirmation humaine requise"
    else:
        verb = "Lecture" if sensitivity == "read" else "Écriture (confirmation requise)"

    description = (
        f"{summary} — {method.upper()} {full_path}. "
        f"{verb}. Outil généré depuis api/openapi.yaml (issue #9746) : "
        f"ne pas éditer à la main, régénérer avec "
        f"`python3 dev-hub/tools/generate-mcp-openapi-tools.py`."
    )

    route: dict[str, Any] = {
        "method": method.upper(),
        "path": full_path,
        "pathParams": sorted(path_params),
        "queryParams": sorted(query_params),
        "hasBody": has_body,
    }
    if is_action:
        route["action"] = True

    return {
        "name": name,
        "description": description,
        "inputSchema": input_schema,
        "outputSchema": {},
        "permission": f"api:{sensitivity}",
        "sensitivity": sensitivity,
        "bc": "BC-23",
        "version": 1,
        "route": route,
    }


def generate(spec: dict[str, Any], include_actions: bool = False) -> dict[str, Any]:
    tools: list[dict[str, Any]] = []
    seen_names: set[str] = set()

    for path, path_item in (spec.get("paths") or {}).items():
        if not isinstance(path_item, dict):
            continue
        if is_excluded_path(path):
            continue

        action = is_action_path(path)
        if action and not include_actions:
            continue

        for method in HTTP_METHODS:
            operation = path_item.get(method)
            if not isinstance(operation, dict):
                continue

            tags = operation.get("tags") or []
            if not isinstance(tags, list) or not (set(tags) & ALLOWED_TAGS):
                continue

            tool = build_tool(spec, method, path, operation, path_item, is_action=action)
            if tool["name"] in seen_names:
                raise ValueError(f"Collision de nom d'outil : {tool['name']} ({method.upper()} {path})")
            seen_names.add(tool["name"])
            tools.append(tool)

    tools.sort(key=lambda item: item["name"])

    info = spec.get("info") or {}
    return {
        "schema_version": 1,
        "generated_by": "python3 dev-hub/tools/generate-mcp-openapi-tools.py",
        "source": "api/openapi.yaml",
        "source_version": str(info.get("version") or "unknown"),
        "allowed_tags": sorted(ALLOWED_TAGS),
        "include_actions": include_actions,
        "tools": tools,
    }


def render(manifest: dict[str, Any]) -> str:
    return json.dumps(manifest, indent=2, ensure_ascii=False, sort_keys=False) + "\n"


def main() -> int:
    parser = argparse.ArgumentParser(description="Génère les outils MCP CRUD depuis api/openapi.yaml.")
    parser.add_argument("--check", action="store_true", help="vérifie que le fichier versionné est à jour (exit 1 sinon)")
    parser.add_argument("--stdout", action="store_true", help="affiche le manifeste sans écrire")
    parser.add_argument("--spec", default=str(SPEC_PATH), help="chemin de la spec OpenAPI")
    parser.add_argument("--output", default=str(OUTPUT_PATH), help="chemin du manifeste généré")
    parser.add_argument(
        "--include-actions",
        action="store_true",
        help="inclut les endpoints d'action comme outils de PROPOSITION (issue #9753)",
    )
    args = parser.parse_args()

    spec_path = Path(args.spec)
    if not spec_path.is_file():
        sys.stderr.write(f"Spec OpenAPI absente : {spec_path}\n")
        return 2

    with spec_path.open("r", encoding="utf-8") as handle:
        spec = yaml.safe_load(handle)

    if not isinstance(spec, dict):
        sys.stderr.write("Spec OpenAPI illisible (racine non-objet).\n")
        return 2

    manifest = generate(spec, include_actions=bool(args.include_actions))
    rendered = render(manifest)
    output_path = Path(args.output)

    if args.stdout:
        sys.stdout.write(rendered)
        return 0

    if args.check:
        if not output_path.is_file():
            sys.stderr.write(
                f"ERREUR : manifeste OpenAPI absent ({output_path}).\n"
                "Régénérer : python3 dev-hub/tools/generate-mcp-openapi-tools.py\n"
            )
            return 1
        current = output_path.read_text(encoding="utf-8")
        if current != rendered:
            sys.stderr.write(
                f"ERREUR : {output_path} diverge de api/openapi.yaml (issue #9746).\n"
                "Régénérer puis committer :\n"
                "  python3 dev-hub/tools/generate-mcp-openapi-tools.py\n"
            )
            return 1
        print(f"OK : manifeste OpenAPI à jour ({len(manifest['tools'])} outils).")
        return 0

    output_path.parent.mkdir(parents=True, exist_ok=True)
    output_path.write_text(rendered, encoding="utf-8")
    print(f"Manifeste écrit : {output_path.relative_to(REPO_ROOT)} ({len(manifest['tools'])} outils).")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
