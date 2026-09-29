#!/usr/bin/env python3
"""Issue #8207 (BOS-018) — garde CI « un down() ne détruit que ce que son up() a créé ».

Contexte : la migration `2026_08_30_000924_6112_create_travel_tourist_sites_table.php`
ne rattrapait qu'`image_asset_id` en up(), mais son down() droppait **dix
colonnes** (`company_id`, `name`, `status`, `created_at`…) créées par la
migration propriétaire `2026_08_30_000018_6112_*`. Un `migrate:rollback` du
batch laissait donc la table vivante, vidée de son schéma, sans aucun
mécanisme de restauration (la migration propriétaire restant « migrée »).
Combiné au `schemaTableExists()` qui rend toute création dupliquée silencieuse
(337/416 migrations au moment du constat), c'est une fabrique de drift
invisible — déjà avéré en prod (référentiel annonces Travel, #7417/#7420).

Règle vérifiée (statique, sans base) :

  1. **down() ne droppe une colonne que si elle figure dans la définition du
     MÊME fichier** — colonnes du `Schema::create` ou ajouts `Schema::table`
     du up(). Une création gardée (`schemaTableExists`) ne rend pas propriétaire
     des colonnes d'une génération antérieure : un fichier qui crée la table
     avec un sous-ensemble de colonnes ne peut pas en dropper d'autres.
  2. **down() ne droppe une table** (`Schema::drop`/`dropIfExists`) **que si le
     up() du même fichier la crée.**

Cas volontairement hors champ (faux positifs évités, documentés) :

  * migrations à nom de table dynamique (`Schema::create($variable)`,
    `createTableIfMissing($var)`) : fichier ignoré, compté « skipped » ;
  * `dropColumn(CONSTANTE)` / colonnes non littérales : non analysables
    statiquement (faux négatifs assumés) ;
  * `DB::statement('CREATE TABLE ...')` brut : hors champ (la garde
    `check-duplicate-schema-create.py` couvre le doublon de création).

Dette legacy : les violations préexistantes hors cluster Travel (périmètre de
l'issue) sont inventoriées dans
`dev-hub/tools/migrations-destructive-down-allowlist.txt` — elles sont
AFFICHÉES sans bloquer ; toute violation NOUVELLE (fichier non listé) est
rouge. Résorption : retirer l'entrée de l'allowlist après correction.

Usage :
    python3 dev-hub/tools/check-migration-destructive-down.py
    python3 dev-hub/tools/check-migration-destructive-down.py --verbose
    python3 dev-hub/tools/check-migration-destructive-down.py --update-allowlist

Exit 0 = vert, 1 = rouge (chaque violation nommée), 2 = erreur technique.
"""
from __future__ import annotations

import argparse
import pathlib
import re
import sys

ROOT = pathlib.Path(__file__).resolve().parents[2]
MIGRATION_GLOB = "api/database/migrations/**/*.php"
ALLOWLIST = ROOT / "dev-hub" / "tools" / "migrations-destructive-down-allowlist.txt"

UP_RE = re.compile(r"function\s+up\s*\(")
DOWN_RE = re.compile(r"function\s+down\s*\(")
ANY_FUNC_RE = re.compile(r"(?:public|private|protected)?\s*function\s+\w+\s*\(")
CREATE_RE = re.compile(r"Schema::create\(\s*'([^']+)'")
TABLE_BLOCK_RE = re.compile(r"Schema::table\(\s*'([^']+)'")
DROP_TABLE_RE = re.compile(r"Schema::drop(?:IfExists)?\(\s*'([^']+)'")
DROP_COLUMN_RE = re.compile(r"\$table->dropColumn\(\s*'([^']+)'")
DROP_COLUMN_LIST_RE = re.compile(r"\$table->dropColumn\(\s*\[([^\]]+)\]\s*\)")
DROP_MORPHS_RE = re.compile(r"\$table->dropMorphs\(\s*'([^']+)'")
COLUMN_ADD_RE = re.compile(
    r"\$table->("
    r"id|increments|char|string|text|mediumText|longText|integer|tinyInteger|"
    r"smallInteger|mediumInteger|bigInteger|unsignedInteger|unsignedTinyInteger|"
    r"unsignedSmallInteger|unsignedMediumInteger|unsignedBigInteger|foreignId|"
    r"foreignIdFor|foreignUlid|foreignUuid|uuid|ulid|boolean|date|dateTime|"
    r"dateTimeTz|time|timeTz|timestamp|timestampTz|year|decimal|double|float|"
    r"json|jsonb|binary|enum|set|ipAddress|macAddress|geometry|point|"
    r"lineString|polygon|multiPoint|multiLineString|multiPolygon|nullable|"
    r"rememberToken"
    r")\s*\(\s*'([^']+)'"
)
TIMESTAMPS_RE = re.compile(r"\$table->(?:timestamps|nullableTimestamps|timestampsTz)\s*\(")
SOFT_DELETES_RE = re.compile(r"\$table->(?:softDeletes|softDeletesTz)\s*\(")
MORPHS_RE = re.compile(r"\$table->(?:morphs|nullableMorphs|uuidMorphs|nullableUuidMorphs)\s*\(\s*'([^']+)'")
DYNAMIC_HINT_RE = re.compile(
    r"Schema::create\(\s*\$|createTableIfMissing\(\s*\$|Schema::table\(\s*\$|"
    r"Schema::drop(?:IfExists)?\(\s*\$"
)


def extract_body(content: str, start_re: re.Pattern[str], stop_res: list[re.Pattern[str]]) -> str:
    """Retourne le corps textuel d'une méthode (approximation par regex suivante)."""
    match = start_re.search(content)
    if match is None:
        return ""
    start = match.end()
    stops = [m.start() for r in stop_res for m in [r.search(content, start)] if m]
    end = min(stops) if stops else len(content)
    return content[start:end]


SEGMENT_BOUNDARY_RE = re.compile(r"Schema::(?:table|create|dropIfExists|drop|rename)\s*\(")


def schema_create_segments(body: str) -> list[tuple[str, str]]:
    """[(table, segment)] — idem pour `Schema::create('t', ...)` : les colonnes
    de la définition initiale comptent dans la « propriété » du fichier."""
    matches = list(CREATE_RE.finditer(body))
    segments = []
    for m in matches:
        nxt = SEGMENT_BOUNDARY_RE.search(body, m.end())
        end = nxt.start() if nxt else len(body)
        segments.append((m.group(1), body[m.start():end]))
    return segments


def schema_table_segments(body: str) -> list[tuple[str, str]]:
    """[(table, segment)] — segment = texte entre un `Schema::table('t'` et le
    prochain appel STRUCTUREL (`Schema::table/create/drop/rename`).

    Les appels introspectifs (`Schema::hasColumn`, `Schema::hasTable`) sont
    fréquents À L'INTÉRIEUR des closures (gardes idempotentes #1613) : ils ne
    doivent PAS borner le segment, sinon les ajouts gardés sont invisibles."""
    matches = list(TABLE_BLOCK_RE.finditer(body))
    segments = []
    for i, m in enumerate(matches):
        nxt = SEGMENT_BOUNDARY_RE.search(body, m.end())
        end = nxt.start() if nxt else len(body)
        segments.append((m.group(1), body[m.start():end]))
    return segments


def added_columns(up_body: str) -> dict[str, set[str]]:
    """table → colonnes définies par le up() (create + ajouts, littéral uniquement)."""
    added: dict[str, set[str]] = {}
    for table, seg in schema_create_segments(up_body) + schema_table_segments(up_body):
        cols = added.setdefault(table, set())
        for m in COLUMN_ADD_RE.finditer(seg):
            if m.group(1) == "id":
                cols.add("id")
            elif m.group(1) == "rememberToken":
                cols.add("remember_token")
            else:
                cols.add(m.group(2))
        if TIMESTAMPS_RE.search(seg):
            cols.update(("created_at", "updated_at"))
        if SOFT_DELETES_RE.search(seg):
            cols.add("deleted_at")
        for m in MORPHS_RE.finditer(seg):
            cols.add(m.group(1) + "_type")
            cols.add(m.group(1) + "_id")
    return added


def dropped_in_down(down_body: str) -> tuple[set[str], list[tuple[str, str]]]:
    """(tables droppées, [(table, colonne) droppée])."""
    tables = set(DROP_TABLE_RE.findall(down_body))
    columns: list[tuple[str, str]] = []
    for table, seg in schema_table_segments(down_body):
        for m in DROP_COLUMN_RE.finditer(seg):
            columns.append((table, m.group(1)))
        for m in DROP_COLUMN_LIST_RE.finditer(seg):
            for col in re.findall(r"'([^']+)'", m.group(1)):
                columns.append((table, col))
        for m in DROP_MORPHS_RE.finditer(seg):
            columns.append((table, m.group(1) + "_type"))
            columns.append((table, m.group(1) + "_id"))
    return tables, columns


def analyse_file(path: pathlib.Path) -> tuple[list[str], bool]:
    """Retourne (violations, skipped_dynamic)."""
    content = path.read_text(encoding="utf-8", errors="replace")
    if DYNAMIC_HINT_RE.search(content):
        return [], True

    up_body = extract_body(content, UP_RE, [DOWN_RE, ANY_FUNC_RE])
    down_body = extract_body(content, DOWN_RE, [ANY_FUNC_RE])
    if not down_body:
        return [], False

    created = set(CREATE_RE.findall(up_body))
    added = added_columns(up_body)
    dropped_tables, dropped_columns = dropped_in_down(down_body)

    violations: list[str] = []
    for table in sorted(dropped_tables):
        if table not in created:
            violations.append(
                f"down() droppe la table '{table}' que son up() ne crée pas"
            )
    for table, col in dropped_columns:
        if col in added.get(table, set()):
            continue
        violations.append(
            f"down() droppe la colonne '{table}.{col}' non définie par son up()"
        )
    return violations, False


def load_allowlist() -> dict[str, str]:
    entries: dict[str, str] = {}
    if not ALLOWLIST.exists():
        return entries
    for line in ALLOWLIST.read_text(encoding="utf-8").splitlines():
        line = line.strip()
        if not line or line.startswith("#"):
            continue
        name, _, comment = line.partition("#")
        entries[name.strip()] = comment.strip()
    return entries


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--verbose", action="store_true")
    parser.add_argument("--update-allowlist", action="store_true",
                        help="réécrit l'allowlist avec les violations courantes")
    args = parser.parse_args()

    files = sorted(ROOT.glob(MIGRATION_GLOB))
    if not files:
        print("ERREUR: aucune migration trouvée", file=sys.stderr)
        return 2

    allowlist = load_allowlist()
    new_violations: list[tuple[str, str]] = []
    known: list[tuple[str, str]] = []
    skipped = 0

    for path in files:
        violations, was_skipped = analyse_file(path)
        if was_skipped:
            skipped += 1
            continue
        rel = path.name
        for v in violations:
            if rel in allowlist:
                known.append((rel, v))
            else:
                new_violations.append((rel, v))

    if args.update_allowlist:
        names = sorted({rel for rel, _ in new_violations} | set(allowlist))
        header = (
            "# Allowlist legacy — garde check-migration-destructive-down.py (#8207).\n"
            "# Régénérée par --update-allowlist. Une entrée = un fichier dont le\n"
            "# down() viole la règle « ne détruire que ce que son up() a créé »,\n"
            "# dette legacy inventoriée hors périmètre du cluster Travel (#8207) :\n"
            "# à corriger fichier par fichier, en retirant l'entrée associée.\n"
        )
        ALLOWLIST.write_text(header + "".join(f"{n}\n" for n in names), encoding="utf-8")
        print(f"allowlist réécrite ({len(names)} entrées) : {ALLOWLIST}")
        return 0

    print(f"Fichiers analysés : {len(files) - skipped} (+ {skipped} ignorés — table dynamique)")
    if known:
        print(f"\nViolations legacy allowlistées ({len(known)}, affichées, non bloquantes) :")
        for rel, v in known:
            print(f"  ~ {rel}: {v}")
    if new_violations:
        print(f"\nVIOLATIONS NOUVELLES ({len(new_violations)}) — rollback destructeur interdit :")
        for rel, v in new_violations:
            print(f"  ✗ {rel}: {v}")
        print(
            "\nUn down() ne doit détruire que ce que le up() du MÊME fichier a créé "
            "(voir #8207).\nSi la violation est une dette legacy assumée et hors périmètre, "
            "l'inventorier via --update-allowlist avec justification dans la PR."
        )
        return 1
    print("\nOK — aucun down() destructeur cross-migration détecté.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
