#!/usr/bin/env python3
"""Issue #7452 — garde CI « une seule migration ne déclare pas deux fois la même table ».

Contexte : le dépôt porte **65 tables déclarées par 2 à 4 migrations** en
`api/database/migrations/`, dont **36 avec des listes de colonnes divergentes**
(voir `docs/audits/MIGRATIONS_DUPLIQUEES_TENANT.md`). Chaque `Schema::create`
est protégé par `if (! schemaTableExists('<table>'))` : **la première migration
exécutée gagne, toutes les suivantes sont silencieusement ignorées**. Le code et
les tests écrits contre la dernière génération échouent alors en
`column "x" does not exist`, souvent masqué par une cascade `25P02`
(« current transaction is aborted ») — c'est la cause des 223 échecs de
`tests/Feature/Travel` (#7452), de la dérive EduManager (#7410) et du
référentiel d'annonces (#7417).

Ce que la garde vérifie :

  1. **Régression par PR** (mode par défaut, base = `origin/main`) : la PR ne
     doit pas introduire
       - une table nouvellement déclarée par plusieurs migrations, ni
       - une divergence de colonnes sur une table déjà dupliquée.
     La dette existante est **affichée** (compteurs) sans bloquer : elle se
     résorbe par les issues #7452 / #7417, pas par une garde qui crie au loup.
  Une table est identifiee par **(schema, nom)** : `edge/` et `tenant/` sont
   deux bases distinctes, une meme table declaree dans les deux n'est pas un
   doublon.

  2. **Mode `--strict`** : toute duplication divergente échoue (utilisé par le
     workflow `workflow_dispatch` pour suivre la résorption, jamais sur PR tant
     que la dette n'est pas résorbée).
  3. **Mode `--audit`** : imprime l'inventaire complet (tables, migrations,
     colonnes absentes du schéma réel) — sert à régénérer le document
     `docs/audits/MIGRATIONS_DUPLIQUEES_TENANT.md`.
  4. `--fail-on-duplicate` : échoue dès qu'une table est déclarée deux fois, même
     sans divergence (option « zéro tolérance » pour un module déjà assaini).

Usage :
    python3 dev-hub/tools/check-duplicate-schema-create.py                # mode PR
    python3 dev-hub/tools/check-duplicate-schema-create.py --base HEAD~1
    python3 dev-hub/tools/check-duplicate-schema-create.py --audit
    python3 dev-hub/tools/check-duplicate-schema-create.py --strict
    python3 dev-hub/tools/check-duplicate-schema-create.py --base ""      # ignore la base

Exit 0 = vert, 1 = rouge (chaque problème est nommé), 2 = erreur technique.
"""
from __future__ import annotations

import argparse
import pathlib
import re
import subprocess
import sys
from collections import defaultdict

ROOT = pathlib.Path(__file__).resolve().parents[2]
MIGRATION_GLOB = "api/database/migrations/**/*.php"
CREATE_RE = re.compile(r"Schema::create\(\s*'([^']+)'\s*,")
COLUMN_RE = re.compile(r"\$table->([A-Za-z_]+)\(\s*'([^']+)'")


def schema_of(path: str) -> str:
    """Schema cible deduit du dossier de la migration (`tenant`, `edge`, ...).

    Les migrations ne sont pas appliquees dans la meme base selon leur dossier :
    `database/migrations/tenant/` cree les tables du schema locataire,
    `database/migrations/edge/` celles de la base SQLite du noeud Edge. Deux
    `Schema::create('edge_nodes')`, l'un dans `edge/` et l'autre dans `tenant/`,
    ne se recouvrent donc **pas** : ce ne sont pas des doublons. Sans cette
    distinction, l'inventaire signalait 4 faux positifs (edge_nodes,
    edge_licenses, sync_logs, sync_queue).
    """
    parts = pathlib.PurePosixPath(path).parts
    if "migrations" in parts:
        index = parts.index("migrations")
        if index + 1 < len(parts) - 1:
            return parts[index + 1]
    return "."


def qualified(schema: str, table: str) -> str:
    return f"{table} [{schema}]" if schema != "." else table


def schema_creates(source: str) -> list[tuple[str, list[str]]]:
    """Retourne [(table, [colonnes])] pour chaque Schema::create du fichier."""
    out: list[tuple[str, list[str]]] = []
    for match in CREATE_RE.finditer(source):
        table = match.group(1)
        start = source.find("{", match.end())
        if start < 0:
            continue
        depth, cursor = 0, start
        while cursor < len(source):
            if source[cursor] == "{":
                depth += 1
            elif source[cursor] == "}":
                depth -= 1
                if depth == 0:
                    break
            cursor += 1
        body = source[start:cursor]
        columns = [c.group(2) for c in COLUMN_RE.finditer(body)]
        out.append((table, columns))
    return out


def inventory_from_worktree() -> dict[str, list[tuple[str, tuple[str, ...]]]]:
    inv: dict[str, list[tuple[str, tuple[str, ...]]]] = defaultdict(list)
    for path in sorted(ROOT.glob(MIGRATION_GLOB)):
        if not path.is_file():
            continue
        for table, columns in schema_creates(path.read_text(encoding="utf-8", errors="replace")):
            rel = str(path.relative_to(ROOT))
            inv[qualified(schema_of(rel), table)].append((rel, tuple(sorted(set(columns)))))
    return dict(inv)


def inventory_from_ref(ref: str) -> dict[str, list[tuple[str, tuple[str, ...]]]]:
    listing = subprocess.run(
        ["git", "ls-tree", "-r", "--name-only", ref, "--", "api/database/migrations"],
        cwd=ROOT, capture_output=True, text=True, check=True,
    ).stdout.split()
    inv: dict[str, list[tuple[str, tuple[str, ...]]]] = defaultdict(list)
    if not listing:
        return {}
    # `git show` par lot pour ne pas payer un process par fichier.
    pretty = subprocess.run(
        ["git", "show", f"--format=", "--no-color", *[f"{ref}:{f}" for f in listing]],
        cwd=ROOT, capture_output=True, text=True,
    )
    blobs = pretty.stdout.split("\x00") if "\x00" in pretty.stdout else None
    if blobs is None:
        # Repli : un `git show` par fichier (robuste, plus lent).
        for path in listing:
            src = subprocess.run(
                ["git", "show", f"{ref}:{path}"], cwd=ROOT, capture_output=True, text=True,
            ).stdout
            for table, columns in schema_creates(src):
                inv[qualified(schema_of(path), table)].append((path, tuple(sorted(set(columns)))))
        return dict(inv)
    for path, blob in zip(listing, blobs):
        for table, columns in schema_creates(blob):
            inv[qualified(schema_of(path), table)].append((path, tuple(sorted(set(columns)))))
    return dict(inv)


def duplicates(inv: dict[str, list[tuple[str, tuple[str, ...]]]]) -> dict[str, list[tuple[str, tuple[str, ...]]]]:
    return {t: v for t, v in inv.items() if len(v) > 1}


def divergent(inv: dict[str, list[tuple[str, tuple[str, ...]]]]) -> dict[str, list[tuple[str, tuple[str, ...]]]]:
    return {t: v for t, v in duplicates(inv).items() if len({cols for _, cols in v}) > 1}


def describe(entries: list[tuple[str, tuple[str, ...]]]) -> str:
    return ", ".join(f"{path} [{'|'.join(cols)}]" for path, cols in entries)


def audit(dups, divs) -> None:
    print(f"# Inventaire des tables déclarées plusieurs fois — {len(dups)} tables dupliquées, "
          f"{len(divs)} divergentes\n")
    for table in sorted(dups):
        entries = dups[table]
        state = "DIVERGENTE" if table in divs else "identique"
        print(f"## `{table}` — {len(entries)}× ({state})")
        winning = set(entries[0][1])
        for path, cols in entries:
            print(f"  - {path} [{'|'.join(cols)}]")
        lost = sorted({c for _, cols in entries for c in cols} - winning)
        if lost:
            print(f"  ⚠️ colonnes absentes du schéma réel (1ʳᵉ migration gagnante) : {', '.join(lost)}")
        print()


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument("--base", default="origin/main",
                        help="ref de comparaison pour la détection de régression (vide = pas de comparaison)")
    parser.add_argument("--audit", action="store_true", help="imprimer l'inventaire complet et sortir 0")
    parser.add_argument("--strict", action="store_true", help="échouer sur toute duplication divergente")
    parser.add_argument("--fail-on-duplicate", action="store_true",
                        help="échouer sur toute table déclarée deux fois, même sans divergence")
    args = parser.parse_args()

    head = inventory_from_worktree()
    if not head:
        print("::error::aucune migration trouvée (chemin api/database/migrations) — garde inopérante.")
        return 2

    head_dups, head_divs = duplicates(head), divergent(head)

    if args.audit:
        audit(head_dups, head_divs)
        return 0

    print(f"=== Garde « une table, une migration » (issue #7452) — {len(head)} tables, "
          f"{len(head_dups)} dupliquées, {len(head_divs)} divergentes ===")

    failures: list[str] = []

    if args.strict:
        for table in sorted(head_divs):
            failures.append(f"[strict] `{table}` est déclarée par {len(head_divs[table])} migrations divergentes "
                            f"({describe(head_divs[table])})")
    elif args.fail_on_duplicate:
        for table in sorted(head_dups):
            failures.append(f"[duplicate] `{table}` est déclarée {len(head_dups[table])}× "
                            f"({describe(head_dups[table])})")
    else:
        base: dict[str, list[tuple[str, tuple[str, ...]]]] = {}
        if args.base:
            probe = subprocess.run(["git", "rev-parse", "--verify", "--quiet", args.base],
                                   cwd=ROOT, capture_output=True, text=True)
            if probe.stdout.strip():
                base = inventory_from_ref(args.base)
            else:
                print(f"::notice::base `{args.base}` introuvable (clone sans historique ?) — "
                      f"comparaison désactivée, mode non bloquant.")
        base_dups, base_divs = duplicates(base), divergent(base)
        for table in sorted(set(head_dups) - set(base_dups)):
            failures.append(f"régression : `{table}` est nouvellement déclarée par {len(head_dups[table])} "
                            f"migrations ({describe(head_dups[table])}) — garder UNE migration par table "
                            f"(les suivantes sont des no-op via `schemaTableExists()`)")
        for table in sorted(set(head_divs) - set(base_divs)):
            failures.append(f"régression : `{table}` devient divergente ({describe(head_divs[table])}) — "
                            f"ajouter/renommer une colonne doit passer par une migration dédiée "
                            f"(`Schema::table`), jamais par un second `Schema::create`")
        # Tables DÉJÀ dupliquées : toute déclaration supplémentaire ou modifiée aggrave la dette
        # (la définition gagnante est celle de la 1ʳᵉ migration exécutée — on ne peut pas la
        # corriger en éditant un `Schema::create`).
        for table in sorted(set(head_dups) & set(base_dups)):
            before = {(path, cols) for path, cols in base[table]}
            after = {(path, cols) for path, cols in head[table]}
            added = sorted(after - before)
            if added:
                failures.append(
                    f"déclaration concurrente ajoutée/modifiée pour `{table}`, déjà déclarée "
                    f"{len(base[table])}× (dette #7452) : "
                    + ", ".join(f"{path} [{'|'.join(cols)}]" for path, cols in added)
                    + " — passer par `Schema::table` (ALTER idempotent) ou consolider les migrations")

    if failures:
        for line in failures:
            print(f"::error::{line}")
        print(f"\n{len(failures)} problème(s). Référence : docs/audits/MIGRATIONS_DUPLIQUEES_TENANT.md "
              f"(#7452), AGENTS.md § Garde migrations.")
        return 1

    print("✅ Aucune nouvelle duplication/divergence introduite.")
    if head_divs:
        print(f"ℹ️  Dette connue (non bloquante ici) : {len(head_dups)} tables dupliquées dont "
              f"{len(head_divs)} divergentes — résorption suivie par #7452 "
              f"(`--strict` pour en faire un gate).")
    return 0


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except BrokenPipeError:  # `| head` côté CI/local : sortie propre
        sys.stderr.close()
        raise SystemExit(0) from None
