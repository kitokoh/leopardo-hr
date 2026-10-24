#!/usr/bin/env python3
"""Garde #8862 — une migration ne peut pas alterer une table creee plus tard.

Laravel trie les migrations par NOM DE FICHIER (`Migrator::getMigrationFiles`
puis `sortBy`). Si un fichier `..._alter_x` porte une sequence *inferieure* au
fichier `..._create_x`, l'ALTER s'execute **avant** la creation de la table.

Combine a la garde d'existence recommandee par le depot (regle #5431) :

    if (! schemaTableExists('x')) { return; }

la migration devient alors un **no-op pour toujours** : elle est enregistree
comme appliquee, et la colonne n'est jamais creee. C'est exactement l'incident
#8862 (quatre colonnes de trois fonctionnalites Restaurant, dont le job de
rappel de reservation et l'opt-in RGPD de la fidelite).

La garde est mecanique et ne demande aucune comprehension du domaine : elle
compare, pour chaque table, le nom du fichier qui la CREE au nom de tout
fichier qui l'ALTERE.

Usage :
    dev-hub/tools/check-migration-alter-before-create.py [--root .] [--strict]

Sortie : exit 0 si aucune anomalie, exit 1 sinon (avec la liste precise).
"""

from __future__ import annotations

import argparse
import collections
import os
import re
import sys

ALLOWLIST = os.path.join(
    os.path.dirname(os.path.abspath(__file__)),
    "migrations-alter-before-create-allowlist.txt",
)

# `Schema::create('x')` — creation explicite de la table.
RE_CREATE = re.compile(r"Schema::create\(\s*['\"]([a-z0-9_]+)['\"]")

# Toute intention d'alteration : `Schema::table('x')` (ALTER reel) et les
# gardes qui la precedent (`schemaTableExists`, `schemaHasColumn`) — une garde
# seule suffit a prouver que le fichier vise la table.
RE_TABLE = re.compile(r"Schema::table\(\s*['\"]([a-z0-9_]+)['\"]")
RE_GUARD_EXISTS = re.compile(r"schemaTableExists\(\s*['\"]([a-z0-9_]+)['\"]")
RE_GUARD_COLUMN = re.compile(r"schemaHasColumn\(\s*['\"]([a-z0-9_]+)['\"]")

# Dossiers de migrations : ensemble de chemins independants, chacun trie seul.
MIGRATION_DIRS = (
    "api/database/migrations/tenant",
    "api/database/migrations/public",
)


def scan_dir(directory: str) -> list[tuple[str, str, str]]:
    """Retourne les triplets (fichier_alter, table, fichier_create) fautifs."""
    files = sorted(f for f in os.listdir(directory) if f.endswith(".php"))

    creates: dict[str, str] = {}
    for name in files:
        path = os.path.join(directory, name)
        with open(path, encoding="utf-8", errors="replace") as handle:
            source = handle.read()
        for match in RE_CREATE.finditer(source):
            # Le premier fichier (ordre alphabetique) qui cree la table gagne.
            creates.setdefault(match.group(1), name)

    offenders: list[tuple[str, str, str]] = []
    for name in files:
        path = os.path.join(directory, name)
        with open(path, encoding="utf-8", errors="replace") as handle:
            source = handle.read()

        tables = set()
        for regex in (RE_TABLE, RE_GUARD_EXISTS, RE_GUARD_COLUMN):
            tables.update(m.group(1) for m in regex.finditer(source))

        # Un fichier qui cree la table ne s'auto-invalide pas : le cas normal
        # est un `Schema::create` suivi d'un `Schema::table` dans le meme
        # fichier (index, contraintes).
        for table in sorted(tables):
            creator = creates.get(table)
            if creator is None:
                continue  # table heritee d'un autre ensemble : hors perimetre
            if creator == name:
                continue
            if creator > name:
                offenders.append((name, table, creator))

    return offenders


def load_allowlist() -> set[str]:
    """Baseline historique documentee : couples `<fichier_alter>|<table>`."""
    if not os.path.isfile(ALLOWLIST):
        return set()

    entries: set[str] = set()
    with open(ALLOWLIST, encoding="utf-8", errors="replace") as handle:
        for line in handle:
            line = line.strip()
            if not line or line.startswith("#"):
                continue
            entries.add(line)
    return entries


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--root", default=os.getcwd())
    parser.add_argument(
        "--strict",
        action="store_true",
        help="echoue aussi si un dossier de migrations est absent",
    )
    args = parser.parse_args()

    allowed = load_allowlist()
    total = 0
    checked_dirs = 0
    suppressed = 0
    per_table: collections.Counter[tuple[str, str]] = collections.Counter()

    for relative in MIGRATION_DIRS:
        directory = os.path.join(args.root, relative)
        if not os.path.isdir(directory):
            if args.strict:
                print(f"::error::Dossier de migrations absent : {relative}")
                return 1
            continue

        checked_dirs += 1
        offenders = scan_dir(directory)
        for alter, table, creator in offenders:
            if f"{alter}|{table}" in allowed:
                suppressed += 1
                continue
            total += 1
            per_table[(relative, table)] += 1
            print(
                f"::error::[{relative}] "
                f"{alter} altere la table '{table}' qui n'est creee que par "
                f"{creator} — la migration ne fera rien (no-op silencieux, #8862)."
            )

    if total:
        print()
        print(f"ECHEC : {total} migration(s) alternalent une table creee plus tard.")
        print("Correctif : renommer le fichier d'ALTER pour que sa sequence soit")
        print("POSTERIEURE a celle du fichier de creation (ex. 2026_10_24_000300_<issue>_),")
        print("ou deplacer l'ajout de colonne dans la migration de creation.")
        return 1

    print(
        f"OK : aucune migration n'altere une table creee plus tard "
        f"({checked_dirs} ensemble(s) de migrations verifie(s)"
        f"{f', {suppressed} cas legacy baseline' if suppressed else ''})."
    )
    return 0


if __name__ == "__main__":
    sys.exit(main())
