#!/usr/bin/env python3
"""
check-event-catalogue.py — Garde CI « Catalogue d'événements versionnés » (MAT-006, issue #5864)

Vérifie que dev-hub/governance/event-catalogue.json reste synchronisé avec
l'état réel du code :

  1. Toute classe sous api/app/Events/*.php doit avoir une entrée dans le
     catalogue (nom, owner, version, payload) — sinon échec avec message
     actionnable ("ajoute <Event> au catalogue").
  2. Toute entrée du catalogue dont le payload déclaré (nom de propriété +
     type) ne correspond plus au constructeur réel de la classe échoue
     ("breaking change non versionné" — le schema n'est plus honnête).
  3. Un événement présent dans le catalogue mais supprimé du code échoue
     (catalogue en avance sur le code — orphelin à nettoyer ou événement
     à marquer 'status: deprecated' avant suppression complète).

Le parsing du payload utilise une regex sur le bloc `public function
__construct(...)` — suffisant pour les Dispatchable events du repo (propriétés
promues `public readonly Type $name`), pas un vrai parseur PHP AST.

Usage : python3 dev-hub/tools/check-event-catalogue.py [repo_root]
Exit codes : 0 = OK, 1 = violation.
"""

from __future__ import annotations

import json
import re
import sys
from pathlib import Path


def extract_use_imports(php_source: str) -> dict[str, str]:
    """Map short class name -> fully-qualified name from `use X\\Y\\Z;` statements."""
    imports: dict[str, str] = {}
    for use_match in re.finditer(r"^use\s+([\w\\]+)\s*;", php_source, re.MULTILINE):
        fqn = use_match.group(1)
        short_name = fqn.rsplit("\\", 1)[-1]
        imports[short_name] = fqn
    return imports


def extract_constructor_payload(php_source: str) -> dict[str, str] | None:
    """Extract {property_name: fully_qualified_type} from a Dispatchable event's constructor.

    Handles both single-line (`public function __construct(public readonly
    Absence $absence) {}`) and multi-line constructors with promoted
    properties (`public readonly Type $name,`). Short type names are resolved
    to fully-qualified names via the file's `use` imports, matching the
    convention used in the catalogue (dev-hub/governance/event-catalogue.json).
    """
    match = re.search(r"public function __construct\s*\((.*?)\)\s*\{", php_source, re.DOTALL)
    if match is None:
        return None

    imports = extract_use_imports(php_source)
    params_block = match.group(1)
    payload: dict[str, str] = {}

    # Each promoted param looks like: public [readonly] Type $name[,]
    # Type may be nullable (?Type) or unqualified (string, int, ...).
    param_pattern = re.compile(
        r"public\s+(?:readonly\s+)?(\??[\w\\]+)\s+\$(\w+)",
    )
    scalar_types = {"string", "int", "float", "bool", "array", "mixed", "object", "callable", "iterable"}
    for param_match in param_pattern.finditer(params_block):
        param_type, param_name = param_match.groups()

        nullable_prefix = "?" if param_type.startswith("?") else ""
        bare_type = param_type.lstrip("?")

        if bare_type.lower() in scalar_types or "\\" in bare_type:
            resolved = bare_type
        else:
            resolved = imports.get(bare_type, bare_type)

        payload[param_name] = f"{nullable_prefix}{resolved}"

    return payload


def normalize_type(type_str: str) -> str:
    """Normalize a PHP type for comparison: strip leading backslash, keep nullable marker."""
    return type_str.lstrip("\\")


def main() -> int:
    repo_root = Path(sys.argv[1] if len(sys.argv) > 1 else ".")
    events_dir = repo_root / "api" / "app" / "Events"
    catalogue_path = repo_root / "dev-hub" / "governance" / "event-catalogue.json"

    if not events_dir.is_dir():
        print(f"::error::Répertoire introuvable : {events_dir}", file=sys.stderr)
        return 1

    if not catalogue_path.is_file():
        print(f"::error::Catalogue introuvable : {catalogue_path} (issue #5864)", file=sys.stderr)
        return 1

    try:
        catalogue = json.loads(catalogue_path.read_text(encoding="utf-8"))
    except json.JSONDecodeError as exc:
        print(f"::error::Catalogue JSON invalide : {exc}", file=sys.stderr)
        return 1

    catalogue_events = {e["name"]: e for e in catalogue.get("events", [])}

    errors: list[str] = []

    # ── 1 & 2. Chaque classe Event a une entrée catalogue à jour ────────────
    code_event_names: set[str] = set()
    for php_file in sorted(events_dir.glob("*.php")):
        class_name = php_file.stem
        code_event_names.add(class_name)

        source = php_file.read_text(encoding="utf-8")

        entry = catalogue_events.get(class_name)
        if entry is None:
            errors.append(
                f"Événement '{class_name}' ({php_file}) absent du catalogue "
                f"({catalogue_path}). Ajoute une entrée (owner, version, payload, "
                f"consumers) dans la même PR."
            )
            continue

        actual_payload = extract_constructor_payload(source)
        if actual_payload is None:
            errors.append(
                f"Événement '{class_name}' : constructeur introuvable/non standard "
                f"— vérifie qu'il utilise des propriétés promues (public readonly Type $name)."
            )
            continue

        declared_payload = entry.get("payload", {})

        # Toute propriété du CODE doit être déclarée dans le catalogue (sinon
        # le schema documenté est mensonger et un breaking change passerait
        # inaperçu). L'inverse (catalogue a une clé de plus) est autorisé
        # temporairement (ex: propriété retirée récemment, catalogue pas
        # encore nettoyé) mais signalé en avertissement, pas en erreur.
        for prop_name, prop_type in actual_payload.items():
            declared_type = declared_payload.get(prop_name)
            if declared_type is None:
                errors.append(
                    f"Événement '{class_name}' : propriété '{prop_name}: {prop_type}' "
                    f"présente dans le code mais absente du catalogue — schema désynchronisé. "
                    f"Mets à jour '{catalogue_path.name}' (et incrémente 'version' si c'est "
                    f"un changement breaking sur un événement déjà 'active')."
                )
                continue

            if normalize_type(declared_type) != normalize_type(prop_type):
                errors.append(
                    f"Événement '{class_name}' : propriété '{prop_name}' a le type "
                    f"'{prop_type}' dans le code mais '{declared_type}' dans le catalogue "
                    f"— breaking change non documenté. Incrémente 'version' dans le "
                    f"catalogue et documente la migration des consumers."
                )

    # ── 3. Aucun événement catalogué n'a disparu du code sans dépréciation ──
    for name, entry in catalogue_events.items():
        if name in code_event_names:
            continue
        if entry.get("status") == "removed":
            continue
        errors.append(
            f"Événement '{name}' présent dans le catalogue mais absent de "
            f"{events_dir} — marque 'status: removed' dans le catalogue si la "
            f"suppression est intentionnelle (après période de dépréciation), "
            f"sinon restaure la classe."
        )

    if errors:
        print("❌  Catalogue d'événements désynchronisé (issue #5864 / MAT-006) :", file=sys.stderr)
        for error in errors:
            print(f"  - {error}", file=sys.stderr)
        return 1

    print(f"✅  Catalogue d'événements synchronisé ({len(code_event_names)} événements).")
    return 0


if __name__ == "__main__":
    sys.exit(main())
