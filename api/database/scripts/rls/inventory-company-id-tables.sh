#!/usr/bin/env bash
#
# #9924 — inventaire des tables `shared_tenants` portant `company_id`.
#
# ## Pourquoi ce script existe
#
# L'ADR-9879 a couvert 4 tables (`bulk_payment_claims`, `pay_slips`,
# `payroll_runs`, `employees`). Mesure : **523 colonnes `company_id`** dans
# `shared_tenants`. Ajouter 500 politiques d'un coup serait une faute — l'ADR
# exige un inventaire des accès légitimes AVANT chaque politique, et une
# politique inerte donne une fausse impression de sécurité.
#
# Ce script produit l'inventaire qui rend la décision possible, table par
# table, sans deviner.
#
# ## Les trois catégories
#
#   (a) A COUVRIR — `company_id` NOT NULL (ou backfillable) et pas de lecture
#       inter-tenant légitime.
#   (b) REFERENCE PARTAGEE — barèmes légaux, catalogues. Filtrer par tenant
#       casserait le calcul de paie. A exclure EXPLICITEMENT, avec la raison.
#   (c) BLOQUEE — `company_id` nullable ET des lignes à NULL. La politique
#       rendrait ces lignes invisibles (`NULL = 'x'` vaut NULL). Un backfill
#       bloquant est un prérequis, comme sur `employees` (#9897).
#
# ## Usage
#
#   database/scripts/rls/inventory-company-id-tables.sh            # tableau
#   database/scripts/rls/inventory-company-id-tables.sh --json     # JSON
#   database/scripts/rls/inventory-company-id-tables.sh --dsn "postgresql://..."
#
# Le DSN par défaut reprend `DB_*` de l'environnement Laravel (via artisan),
# donc le script mesure la base que l'application utilise réellement.
#
# Code de sortie : 0 toujours (c'est un rapport, pas un garde-fou).

set -uo pipefail

JSON_MODE=0
DSN=""

while [ $# -gt 0 ]; do
    case "$1" in
        --json) JSON_MODE=1; shift ;;
        --dsn) DSN="${2:-}"; shift 2 ;;
        *) echo "Option inconnue : $1" >&2; exit 2 ;;
    esac
done

cd "$(dirname "$0")/../../.." || exit 1

if [ -z "$DSN" ]; then
    # Réutilise la configuration Laravel : on mesure la base RÉELLE, pas une
    # base supposée. `php artisan tinker` imprimerait du bruit ; on lit la
    # config directement.
    DSN="$(php -r '
        require "vendor/autoload.php";
        $app = require "bootstrap/app.php";
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        $c = config("database.connections.".config("database.default"));
        printf("postgresql://%s:%s@%s:%s/%s",
            $c["username"], $c["password"], $c["host"], $c["port"], $c["database"]);
    ' 2>/dev/null)"
fi

if [ -z "$DSN" ]; then
    echo "Impossible de determiner le DSN. Passer --dsn." >&2
    exit 2
fi

SQL=$(cat <<'SQL'
WITH cols AS (
    SELECT c.relname AS table_name,
           col.is_nullable
      FROM pg_class c
      JOIN pg_namespace n ON n.oid = c.relnamespace
      JOIN information_schema.columns col
        ON col.table_schema = n.nspname AND col.table_name = c.relname
     WHERE n.nspname = 'shared_tenants'
       AND c.relkind = 'r'
       AND col.column_name = 'company_id'
)
SELECT cols.table_name,
       cols.is_nullable,
       cls.relrowsecurity,
       cls.relforcerowsecurity,
       (SELECT count(*) FROM pg_policy p WHERE p.polrelid = cls.oid) AS policies,
       (xpath('/row/n/text()', query_to_xml(
            format('SELECT count(*) AS n FROM shared_tenants.%I WHERE company_id IS NULL', cols.table_name),
            false, true, '')))[1]::text::bigint AS null_rows
  FROM cols
  JOIN pg_class cls ON cls.relname = cols.table_name
  JOIN pg_namespace n2 ON n2.oid = cls.relnamespace AND n2.nspname = 'shared_tenants'
 ORDER BY cols.table_name;
SQL
)

if [ "$JSON_MODE" -eq 1 ]; then
    psql "$DSN" -t -A -F$'\t' -c "$SQL" | python3 -c '
import json, sys

rows = []
for line in sys.stdin:
    line = line.rstrip("\n")
    if not line:
        continue
    name, nullable, enabled, forced, policies, null_rows = line.split("\t")
    covered = enabled == "t" and forced == "t" and int(policies) > 0
    null_rows = int(null_rows or 0)

    if covered:
        category = "couvert"
    elif nullable == "YES" and null_rows > 0:
        category = "bloque (backfill requis)"
    elif nullable == "YES":
        category = "a couvrir (nullable, 0 NULL)"
    else:
        category = "a couvrir"

    rows.append({
        "table": name,
        "company_id_nullable": nullable == "YES",
        "rls_enabled": enabled == "t",
        "rls_forced": forced == "t",
        "policies": int(policies),
        "null_rows": null_rows,
        "category": category,
    })

from collections import Counter
counts = Counter(r["category"] for r in rows)
print(json.dumps({
    "total_tables": len(rows),
    "by_category": dict(counts),
    "tables": rows,
}, indent=2, ensure_ascii=False))
'
    exit 0
fi

psql "$DSN" -c "
WITH cols AS (
    SELECT c.relname AS table_name, col.is_nullable
      FROM pg_class c
      JOIN pg_namespace n ON n.oid = c.relnamespace
      JOIN information_schema.columns col
        ON col.table_schema = n.nspname AND col.table_name = c.relname
     WHERE n.nspname = 'shared_tenants'
       AND c.relkind = 'r'
       AND col.column_name = 'company_id'
)
SELECT cols.table_name AS \"table\",
       cols.is_nullable AS nullable,
       CASE WHEN cls.relrowsecurity AND cls.relforcerowsecurity
                 AND (SELECT count(*) FROM pg_policy p WHERE p.polrelid = cls.oid) > 0
            THEN 'oui' ELSE 'non' END AS \"RLS couverte\",
       (xpath('/row/n/text()', query_to_xml(
            format('SELECT count(*) AS n FROM shared_tenants.%I WHERE company_id IS NULL', cols.table_name),
            false, true, '')))[1]::text AS \"lignes NULL\",
       CASE
         WHEN cls.relrowsecurity AND cls.relforcerowsecurity
              AND (SELECT count(*) FROM pg_policy p WHERE p.polrelid = cls.oid) > 0 THEN 'couvert'
         WHEN cols.is_nullable = 'YES'
              AND (xpath('/row/n/text()', query_to_xml(
                    format('SELECT count(*) AS n FROM shared_tenants.%I WHERE company_id IS NULL', cols.table_name),
                    false, true, '')))[1]::text::bigint > 0 THEN 'BLOQUE (backfill)'
         WHEN cols.is_nullable = 'YES' THEN 'a couvrir (nullable, 0 NULL)'
         ELSE 'a couvrir'
       END AS \"categorie\"
  FROM cols
  JOIN pg_class cls ON cls.relname = cols.table_name
  JOIN pg_namespace n2 ON n2.oid = cls.relnamespace AND n2.nspname = 'shared_tenants'
 ORDER BY \"categorie\", cols.table_name;
"

echo
echo "Lecture :"
echo "  couvert                  -> ENABLE + FORCE + politique : rien a faire"
echo "  a couvrir                -> company_id NOT NULL, pas de lecture inter-tenant connue"
echo "  a couvrir (nullable)     -> nullable mais 0 ligne a NULL : backfill trivial puis politique"
echo "  BLOQUE (backfill)        -> des lignes a NULL : la politique les rendrait invisibles"
echo "                              (NULL = 'x' vaut NULL) — backfill bloquant d'abord"
echo
echo "⚠️  Tant que le role applicatif est SUPERUSER, toute politique est INERTE."
echo "    Verifier avec: php artisan tenant:rls-status"
