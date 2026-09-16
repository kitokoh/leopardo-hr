#!/usr/bin/env python3
"""Garde « tout fichier JSON/ARB suivi se parse » — issues #7583 / #7455.

## Le trou qu'elle ferme

Résoudre un conflit de catalogue i18n avec `git merge-file --union` **casse le
JSON** : `--union` concatène les deux côtés d'un conflit *ligne à ligne*, ce qui
duplique virgules et clés dans un objet ou un tableau. Constat du drain #7562 :
**13 à 17 fichiers JSON/ARB invalides** (`Expecting ',' delimiter`), et les
synchronisateurs du dépôt (`shared/i18n/sync/*.js`) plantaient **au démarrage**
— parce qu'ils *relisent* ces fichiers, dont `shared/i18n/versions/versions.json`
lu **avant** toute écriture. Un fichier cassé ne restait donc pas une curiosité
locale : il bloquait la chaîne de synchronisation.

La méthode de résolution (merge **profond** via `json.loads` des deux côtés, puis
**régénération** par les synchronisateurs, puis `validators/validate.js`) est
documentée au §6.3 de `docs/GOUVERNANCE/PROTOCOLE_LOTS_MULTI_AGENTS.md`. Ce
qu'elle demande comme **contrôle final**, c'est exactement cette garde :
« **tous** les `*.json`/`*.arb` suivis se parsent ».

Pourquoi elle vit dans `actionlint.yml` (check **requis**, sans filtre de
chemins) plutôt que dans `i18n-enterprise.yml` : ce dernier est filtré par
`paths:` — un JSON cassé **hors** de ces chemins (dev-hub, config, `front/web`
hors `src/`) y échapperait silencieusement. Ici, tout PR est couvert.

## Usage

    python3 dev-hub/tools/check-json-parsable.py               # le dépôt courant
    python3 dev-hub/tools/check-json-parsable.py --self-test   # éprouve la garde

L'auto-test est une **épreuve par mutation** : il rejoue la logique sur des
fixtures fabriquées et exige que chaque défaut soit détecté (et qu'un fichier
sain passe). Une garde qui ne sait pas échouer ne prouve rien.
"""

from __future__ import annotations

import json
import subprocess
import sys
import tempfile
from pathlib import Path

TARGET_SUFFIXES = (".json", ".arb")


def tracked_targets(root: Path) -> list[Path]:
    """Fichiers .json/.arb **suivis par git** (donc livrables)."""
    out = subprocess.run(
        ["git", "ls-files", "-z"],
        cwd=str(root),
        capture_output=True,
        text=True,
        check=True,
    ).stdout
    return [
        root / name
        for name in out.split("\0")
        if name.endswith(TARGET_SUFFIXES)
    ]


def check_files(paths: list[Path], root: Path | None = None) -> list[tuple[str, str]]:
    """Renvoie la liste (chemin, raison) des fichiers qui ne parsent pas."""
    problems: list[tuple[str, str]] = []

    for path in paths:
        label = str(path.relative_to(root)) if root and path.is_relative_to(root) else str(path)
        try:
            json.loads(path.read_text(encoding="utf-8"))
        except json.JSONDecodeError as exc:
            problems.append(
                (label, f"{exc.msg} — ligne {exc.lineno}, colonne {exc.colno}")
            )
        except OSError as exc:
            problems.append((label, f"illisible : {exc}"))
        except ValueError as exc:
            problems.append((label, str(exc)))

    return problems


def run_self_test() -> int:
    """Épreuve par mutation : chaque fixture doit produire le verdict attendu."""
    with tempfile.TemporaryDirectory() as tmp:
        root = Path(tmp)

        valid = root / "valid.json"
        valid.write_text('{\n  "a": 1,\n  "b": [1, 2]\n}\n', encoding="utf-8")

        # Le dommage exact de `git merge-file --union` : virgule dupliquée.
        union_damage = root / "union.json"
        union_damage.write_text('{\n  "a": 1,\n,\n  "b": [1, 2]\n}\n', encoding="utf-8")

        # Clé dupliquée sur un objet : syntaxiquement valide, verdict « parse OK »
        # — assumé et documenté : cette garde juge la SYNTAXE, pas l'ambiguïté.
        dup_key = root / "dup.json"
        dup_key.write_text('{\n  "a": 1,\n  "a": 2\n}\n', encoding="utf-8")

        trailing = root / "trailing.arb"
        trailing.write_text('{\n  "greeting": "bonjour"\n', encoding="utf-8")

        cases = [
            ("JSON sain -> vert", [valid], 0),
            ("virgule dupliquée (dommage #7562) -> rouge", [union_damage], 1),
            ("fichier tronqué -> rouge", [trailing], 1),
            ("clé dupliquée (syntaxe valide) -> vert", [dup_key], 0),
            ("mélange sain + cassé -> rouge", [valid, union_damage], 1),
        ]

        failures = 0
        for name, files, expected in cases:
            got = 1 if check_files(files, root) else 0
            ok = got == expected
            failures += 0 if ok else 1
            print(f"{'  ok  ' if ok else ' FAIL '} {name}" + ("" if ok else f" (attendu={expected} obtenu={got})"))

        print(f"\nSelf-test: {len(cases) - failures}/{len(cases)} cas conformes.")
        return 0 if failures == 0 else 1


def main(argv: list[str]) -> int:
    if "--self-test" in argv:
        return run_self_test()

    root = Path.cwd()
    try:
        targets = tracked_targets(root)
    except (subprocess.CalledProcessError, FileNotFoundError) as exc:
        print(f"::error::check-json-parsable : git indisponible ({exc})", file=sys.stderr)
        return 1

    problems = check_files(targets, root)

    if not problems:
        print(f"check-json-parsable : OK — {len(targets)} fichier(s) .json/.arb suivis se parsent.")
        return 0

    print(
        "check-json-parsable : JSON/ARB invalide(s) — un catalogue cassé bloque les "
        "synchronisateurs (issue #7583) :\n",
        file=sys.stderr,
    )
    for label, reason in problems:
        print(f"  - {label} : {reason}", file=sys.stderr)
    print(
        "\nRemède (§6.3 du protocole lots multi-agents) : ne PAS résoudre un conflit de "
        "catalogue avec `git merge-file --union`. Merger en profondeur (`json.loads` des "
        "deux côtés) puis régénérer par shared/i18n/sync/*.js et valider avec "
        "shared/i18n/validators/validate.js.",
        file=sys.stderr,
    )
    return 1


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))
