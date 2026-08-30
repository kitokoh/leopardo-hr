#!/usr/bin/env bash
# check-migration-sql-quotes.sh — Garde d'échappement des apostrophes SQL.
#
# Issue #6418 : des `COMMENT ON TABLE ... IS '...'` contenaient des apostrophes
# ASCII non échappées (`d'articles`) → SQLSTATE 42601 au `leopardo:migrate`
# tenant (175 tests Feature en échec au bootstrap). La convention du dépôt est
# d'utiliser l'apostrophe typographique (’) pour le texte des commentaires,
# ou l'échappement SQL (`''`) dans une chaîne simple quote.
#
# Vérifie sur TOUTES les migrations que :
#   - aucun `DB::statement("COMMENT ON TABLE ... IS '...'")` ne contient une
#     apostrophe ASCII entre deux caractères de mot (apostrophe « de mot »),
#     qui casserait la chaîne SQL simple quote.
#
# Usage : check-migration-sql-quotes.sh [chemin-des-migrations]
#   défaut : api/database/migrations
# Sortie 0 = OK ; sortie 1 = violations (mode --strict).
set -uo pipefail

MIGRATIONS_DIR="${1:-api/database/migrations}"
STRICT=0
[ "${2:-}" = "--strict" ] && STRICT=1

if [ ! -d "$MIGRATIONS_DIR" ]; then
  echo "::error::dossier migrations introuvable : $MIGRATIONS_DIR"
  exit 2
fi

violations=0

# Boucle sur les fichiers .php contenant un COMMENT ON TABLE
while IFS= read -r file; do
  # Ligne(s) COMMENT ON TABLE avec apostrophe ASCII « de mot » dans la chaîne SQL
  # (typographie ’ = OK ; apostrophe ASCII entre lettres = chaîne SQL cassée).
  while IFS= read -r line; do
    [ -z "$line" ] && continue
    echo "::error::apostrophe SQL non échappée dans $file : $line"
    violations=$((violations + 1))
  done < <(grep -nE "COMMENT ON TABLE .*IS '[^']*[a-zA-Z0-9]'[a-zA-Z0-9]" "$file" || true)
done < <(grep -rlE "COMMENT ON TABLE" "$MIGRATIONS_DIR" --include="*.php" 2>/dev/null || true)

if [ "$violations" -eq 0 ]; then
  echo "  OK — aucune apostrophe SQL non échappée dans les migrations."
  exit 0
fi

echo "  $violations violation(s) — échapper avec '' ou utiliser ’ (typographique)."
[ "$STRICT" -eq 1 ] && exit 1
exit 0
