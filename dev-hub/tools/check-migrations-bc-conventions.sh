#!/usr/bin/env bash
# check-migrations-bc-conventions.sh — MAT-005 / conventions migrations BC.
#
# Garde de conformité des migrations Laravel du monorepo (référentiel
# docs/architecture/MIGRATIONS_CONVENTIONS.md) :
#
#   1. Laravel est l'UNIQUE chaîne de migration : aucun DDL SQL autonome
#      (*.sql) ni marqueur Flyway/Prisma/Knex sous api/database/.
#   2. Nommage #5431 : toute migration créée après GUARD_DATE respecte
#      `YYYY_MM_DD_0000NN_<issue>_<slug>.php` (référence d'issue obligatoire).
#   3. Forme : `return new class extends Migration` + `down()` implémenté
#      (rollback/fresh cohérents).
#   4. Réentrance #1613 : une migration tenant appelant `Schema::create()`
#      doit d'abord vérifier `schemaTableExists()`.
#   5. Isolation tenant/public (constitution §II) : une migration tenant ne
#      crée jamais de FK vers `companies` (table public).
#
# Périmètre : les règles 2/4 s'appliquent aux migrations créées à partir de
# GUARD_DATE (2026-08-18, date d'introduction des conventions actuelles) pour
# ne pas sanctionner la dette historique. Les règles 1/3/5 s'appliquent à
# tout l'arbre (zéro violation à ce jour).
#
# Usage : dev-hub/tools/check-migrations-bc-conventions.sh [repo_root]
# Exit 1 si une violation est détectée.
#
# Auto-test : bash dev-hub/tools/tests/check-migrations-bc-conventions.test.sh

set -euo pipefail

ROOT="${1:-$(git rev-parse --show-toplevel 2>/dev/null || true)}"
if [[ -z "${ROOT}" ]]; then
  ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
fi
cd "${ROOT}"

GUARD_DATE="2026_08_18"
MIGRATIONS_DIR="api/database/migrations"
VIOLATIONS=0

fail() {
  echo "VIOLATION: $1"
  VIOLATIONS=$((VIOLATIONS + 1))
}

if [[ ! -d "${MIGRATIONS_DIR}" ]]; then
  echo "OK: pas de répertoire de migrations (${MIGRATIONS_DIR}) — rien à contrôler."
  exit 0
fi

# ── 1. Chaîne unique : aucun DDL SQL autonome / marqueur d'outil parallèle ──
if find api/database -name "*.sql" -type f 2>/dev/null | grep -q .; then
  while IFS= read -r f; do
    if grep -qiE "CREATE TABLE|ALTER TABLE" "${f}"; then
      fail "DDL SQL autonome interdit (Laravel = chaîne unique) : ${f}"
    fi
  done < <(find api/database -name "*.sql" -type f 2>/dev/null)
fi

if rg -l -i "flyway|prisma|knex" "${MIGRATIONS_DIR}" --glob '!*.php' >/dev/null 2>&1; then
  while IFS= read -r f; do
    fail "marqueur de chaîne de migration parallèle (Flyway/Prisma/Knex) : ${f}"
  done < <(rg -l -i "flyway|prisma|knex" "${MIGRATIONS_DIR}" --glob '!*.php' 2>/dev/null)
fi

# ── Collecte des migrations (public + tenant + edge) ─────────────────────────
mapfile -t ALL_MIGRATIONS < <(find "${MIGRATIONS_DIR}" -name "*.php" -type f 2>/dev/null | sort)

if [[ "${#ALL_MIGRATIONS[@]}" -eq 0 ]]; then
  echo "OK: aucune migration PHP — rien à contrôler."
  exit 0
fi

for f in "${ALL_MIGRATIONS[@]}"; do
  base="$(basename "${f}")"
  datepart="${base%%_*}"

  is_new=false
  if [[ "${datepart}" > "${GUARD_DATE}" || "${datepart}" == "${GUARD_DATE}" ]]; then
    is_new=true
  fi

  # ── 2. Nommage #5431 (migrations nouvelles) ────────────────────────────────
  if [[ "${is_new}" == true ]]; then
    if ! [[ "${base}" =~ ^[0-9]{4}_[0-9]{2}_[0-9]{2}_[0-9]{6}_[0-9]+_[a-z0-9_]+\.php$ ]]; then
      fail "nommage #5431 (YYYY_MM_DD_0000NN_<issue>_<slug>.php, issue obligatoire) : ${f}"
    fi
  fi

  # ── 3. Forme : return new class + down() ───────────────────────────────────
  if ! grep -q "return new class extends Migration" "${f}"; then
    fail "forme 'return new class extends Migration' attendue : ${f}"
  fi
  if ! grep -q "function down" "${f}"; then
    fail "down() manquant (rollback cohérent requis) : ${f}"
  fi

  # ── 4. Réentrance tenant : schemaTableExists avant Schema::create ──────────
  if [[ "${is_new}" == true && "${f}" == *"/tenant/"* ]]; then
    if grep -q "Schema::create(" "${f}" && ! grep -q "schemaTableExists(" "${f}"; then
      fail "réentrance #1613 : Schema::create() sans garde schemaTableExists() : ${f}"
    fi
  fi

  # ── 5. Isolation tenant/public : aucune FK vers companies ──────────────────
  if [[ "${f}" == *"/tenant/"* ]]; then
    if grep -qE "on\('companies'\)|on\(\"companies\"\)" "${f}"; then
      fail "FK vers companies (table public) interdite depuis une migration tenant : ${f}"
    fi
  fi
done

if [[ "${VIOLATIONS}" -gt 0 ]]; then
  echo "FAIL: ${VIOLATIONS} violation(s) des conventions de migrations BC (MIGRATIONS_CONVENTIONS.md)."
  exit 1
fi

echo "OK: ${#ALL_MIGRATIONS[@]} migrations conformes aux conventions BC (nommage, forme, réentrance, isolation, chaîne unique)."
exit 0
