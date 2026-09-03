#!/usr/bin/env bash
#
# check-entrypoint-reset-guard.test.sh — tests de la garde #6537 :
# RESET_TEST_DB_ONCE=true est interdit quand APP_ENV=production
# (le reset one-shot DROP toutes les tables/schémas — fail-closed).
#
# Usage : bash dev-hub/tools/tests/check-entrypoint-reset-guard.test.sh
set -uo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"
ENTRYPOINT="${ROOT}/api/docker-entrypoint.sh"

pass=0
fail=0
ok()   { pass=$((pass+1)); printf '  ✅ %s\n' "$*"; }
ko()   { fail=$((fail+1)); printf '  ❌ %s\n' "$*"; }

# Extrait UNIQUEMENT le bloc de garde de maybe_reset_test_database_once()
# (depuis le script réel, pour éviter toute dérive), puis lui ajoute un
# « exit 0 » pour les cas où la garde laisse passer.
guard_block() {
  python3 - "$ENTRYPOINT" << 'PY'
import re, sys
src = open(sys.argv[1]).read()
# du début de la fonction jusqu'au second « if [ "$reset_once" != "true" ] » inclus
m = re.search(
    r'^(maybe_reset_test_database_once\(\) \{.*?if \[ "\$reset_once" != "true" \]; then\n(?:[^\n]*\n){2})',
    src, re.S | re.M)
assert m, 'bloc de garde introuvable dans docker-entrypoint.sh'
block = m.group(1)
# referme la fonction (le bloc réel continue avec le reset destructif)
print(block + '\n}\nmaybe_reset_test_database_once\n')
PY
}

echo "== Garde #6537 — RESET_TEST_DB_ONCE en production =="

# CAS 1 : APP_ENV=production + RESET_TEST_DB_ONCE=true → exit 1 (fail-closed)
if APP_ENV=production RESET_TEST_DB_ONCE=true bash -c "$(guard_block)" >/dev/null 2>&1; then
  ko "production + reset=true devrait refuser (exit 1)"
else
  ok "production + reset=true → refuse de démarrer"
fi

# CAS 2 : APP_ENV=staging + RESET_TEST_DB_ONCE=true → la garde laisse passer
if APP_ENV=staging RESET_TEST_DB_ONCE=true bash -c "$(guard_block)" >/dev/null 2>&1; then
  ok "staging + reset=true → non bloqué par la garde"
else
  ko "staging + reset=true ne devrait pas être bloqué"
fi

# CAS 3 : reset non défini → retour immédiat sans erreur
if bash -c "$(guard_block)" >/dev/null 2>&1; then
  ok "reset non défini → aucun effet"
else
  ko "reset non défini ne devrait pas échouer"
fi

echo ""
echo "==================================="
echo "Pass: ${pass}  Fail: ${fail}"
[ "${fail}" -eq 0 ]
