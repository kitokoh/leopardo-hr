#!/usr/bin/env python3
"""Garde #8396 — une fixture de test ne peut pas laisser une table canonique REDUITE.

Contexte (incident #8396, cluster `edge_version`) :

    `tests/Feature/Edge/EdgeSyncDedupTest` creait `edge_nodes` avec un
    SOUS-ENSEMBLE de colonnes (10 sur 18, sans `edge_version`) des que la
    table canonique etait absente, puis la laissait EN PLACE. Les classes
    `EdgeSync\\*` suivantes du meme worker PHPUnit inserant un `EdgeNode`
    complet echouaient alors en `SQLSTATE[42703] column "edge_version" ...
    does not exist` — 12 echecs directs + 8 cascades `25P02`.

Le depot connait deja le motif legitime : un test qui veut un schema LEGACY
(par ex. `EdgeSilentNodeDetectionTest`, bigint + `node_id`) remplace la table
canonique pour la duree du test, puis la DROP dans son `tearDown()` et appelle
`recreateCanonicalEdgeNodesTable()` — la table canonique est donc restauree
pour les classes suivantes. Ce motif est sain et n'est PAS signale ici.

La garde est mecanique et ne demande aucune comprehension du domaine :

    Pour chaque fichier de test qui `Schema::create` une table EGALEMENT
    creee par une migration tenant, si la definition du test omet des
    colonnes canoniques, alors le fichier DOIT dropper cette table
    (`DROP TABLE ... <table>`) — sinon la table reduite fuit vers les
    classes suivantes du worker.

Usage :
    dev-hub/tools/check-test-fixture-schema-parity.py [--root .] [--strict]

Sortie : exit 0 si aucune anomalie, exit 1 sinon (avec la liste precise).
"""

from __future__ import annotations

import argparse
import glob
import os
import re
import sys

# Colonnes implicites ajoutees par les helpers Blueprint.
IMPLICIT = {"id", "created_at", "updated_at", "deleted_at"}

CREATE_RE = re.compile(
    r"Schema::create\(\s*'([a-z0-9_]+)'\s*,\s*function[^{]*\{(.*?)\n\s*\}\);",
    re.S,
)
COL_RE = re.compile(r"\$table->[a-zA-Z]+\(\s*'([a-z0-9_]+)'")
HELPER_RE = re.compile(r"\$table->(id|timestamps|softDeletes|uuid)\(\s*\)")


def canonical_columns(root: str) -> dict[str, set[str]]:
    """Colonnes canoniques par table, d'apres les migrations tenant."""
    canon: dict[str, set[str]] = {}
    pattern = os.path.join(root, "api/database/migrations/tenant/*.php")
    for path in glob.glob(pattern):
        src = open(path, encoding="utf-8", errors="replace").read()
        for match in CREATE_RE.finditer(src):
            table, body = match.group(1), match.group(2)
            cols = set(COL_RE.findall(body))
            if HELPER_RE.search(body):
                cols |= IMPLICIT
            canon.setdefault(table, set()).update(cols)
    return canon


def test_files(root: str) -> list[str]:
    out: list[str] = []
    for sub in ("Feature", "Unit"):
        out += glob.glob(os.path.join(root, f"api/tests/{sub}/**/*.php"), recursive=True)
    return sorted(out)


def load_allowlist(root: str) -> set[tuple[str, str]]:
    """Baseline documentee : cas anterieurs a la garde (dette assumee)."""
    path = os.path.join(root, "dev-hub/tools/test-fixture-schema-parity-allowlist.txt")
    if not os.path.exists(path):
        return set()
    out: set[tuple[str, str]] = set()
    for line in open(path, encoding="utf-8"):
        line = line.strip()
        if not line or line.startswith("#"):
            continue
        if "::" in line:
            rel, table = line.split("::", 1)
            out.add((rel.strip(), table.strip()))
    return out


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--root", default=".")
    parser.add_argument("--strict", action="store_true")
    args = parser.parse_args()

    root = os.path.abspath(args.root)
    canon = canonical_columns(root)
    if not canon:
        print("OK: aucune migration tenant trouvee — rien a controler.")
        return 0

    allowlist = load_allowlist(root)
    violations: list[tuple[str, str, list[str]]] = []
    baselined = 0

    for path in test_files(root):
        src = open(path, encoding="utf-8", errors="replace").read()
        rel = os.path.relpath(path, root)

        for match in CREATE_RE.finditer(src):
            table, body = match.group(1), match.group(2)
            if table not in canon:
                continue

            cols = set(COL_RE.findall(body))
            if HELPER_RE.search(body):
                cols |= IMPLICIT

            missing = canon[table] - cols
            if not missing:
                continue

            # Motif legitime 1 : le fichier droppe la table (tearDown) — la
            # canonique est alors reconstruite pour les classes suivantes.
            drops = re.search(
                rf"DROP\s+TABLE\s+(?:IF\s+EXISTS\s+)?(?:[\w\".]*\.)?\"?{re.escape(table)}\"?",
                src,
                re.I,
            )
            if drops:
                continue

            # Motif legitime 2 : le fichier reconstruit le schema COMPLET a
            # chaque test (`RefreshTenantDatabase` = migrate:fresh + purge des
            # deux schemas). La table reduite ne survit donc pas a la classe :
            # le prochain test repart d'un schema migre. C'est le cas des
            # suites CRM / Contracts / Seed, qui declarent leurs tables dans
            # un helper local pour rester hermetiques.
            if re.search(r"\buse\s+RefreshTenantDatabase\b", src):
                continue

            if (rel, table) in allowlist:
                baselined += 1
                continue

            violations.append((rel, table, sorted(missing)))

    if not violations:
        print(
            f"OK: aucune fixture ne laisse une table canonique reduite "
            f"({len(canon)} tables canoniques controlees, "
            f"{baselined} cas legacy baseline)."
        )
        return 0

    print("::error::Fixture(s) de test laissant une table canonique REDUITE (#8396) :")
    for rel, table, missing in violations:
        print(f"  {rel} :: `{table}` omet {len(missing)} colonne(s) : {', '.join(missing)}")
    print()
    print(
        "Remede : soit utiliser la fixture canonique (`CreatesMvpSchema`),\n"
        "soit dropper la table dans `tearDown()` pour que la canonique soit\n"
        "reconstruite (motif `recreateCanonicalEdgeNodesTable()`)."
    )
    return 1


if __name__ == "__main__":
    sys.exit(main())
