#!/usr/bin/env bash
#
# check-entrypoint-reset-guard.test.sh — tests des gardes #6537 + #7647 :
# - #6537 : RESET_TEST_DB_ONCE=true est interdit quand APP_ENV=production
#   (le reset one-shot DROP toutes les tables/schémas — fail-closed).
# - #7647 : le tier dev tournant en APP_ENV=production, APP_ENV ne peut pas
#   distinguer dev et prod — RESET_TEST_DB_ONCE exige DEPLOY_TIER=dev
#   explicite (absente, vide ou autre valeur => refus fail-closed).
#
# Usage : bash dev-hub/tools/tests/check-entrypoint-reset-guard.test.sh
set -uo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"
ENTRYPOINT="${ROOT}/api/docker-entrypoint.sh"

pass=0
fail=0
ok()   { pass=$((pass+1)); printf '  ✅ %s\n' "$*"; }
ko()   { fail=$((fail+1)); printf '  ❌ %s\n' "$*"; }

# Extrait UNIQUEMENT le bloc de gardes de maybe_reset_test_database_once()
# (depuis le script réel, pour éviter toute dérive) : du début de la fonction
# jusqu'à la ligne `reset_key=` exclue (tout ce qui précède le reset
# destructif), puis referme la fonction avec un « return 0 » pour les cas où
# les gardes laissent passer.
guard_block() {
  python3 - "$ENTRYPOINT" << 'PY'
import re, sys
src = open(sys.argv[1]).read()
m = re.search(
    r'^(maybe_reset_test_database_once\(\) \{.*?)\n[ \t]*reset_key=',
    src, re.S | re.M)
assert m, 'bloc de gardes introuvable dans docker-entrypoint.sh'
block = m.group(1)
print(block + '\n    return 0\n}\nmaybe_reset_test_database_once\n')
PY
}

echo "== Gardes #6537 + #7647 — RESET_TEST_DB_ONCE =="

# CAS 1 : APP_ENV=production + RESET_TEST_DB_ONCE=true → exit 1 (fail-closed
# #6537, même avec DEPLOY_TIER=dev : les gardes s'additionnent).
if APP_ENV=production DEPLOY_TIER=dev RESET_TEST_DB_ONCE=true bash -c "$(guard_block)" >/dev/null 2>&1; then
  ko "production + reset=true devrait refuser (exit 1), même avec DEPLOY_TIER=dev"
else
  ok "production + reset=true → refuse de démarrer (garde #6537 conservée)"
fi

# CAS 2 : APP_ENV=staging + reset=true + DEPLOY_TIER=dev → laisse passer
if APP_ENV=staging DEPLOY_TIER=dev RESET_TEST_DB_ONCE=true bash -c "$(guard_block)" >/dev/null 2>&1; then
  ok "staging + reset=true + DEPLOY_TIER=dev → non bloqué par les gardes"
else
  ko "staging + reset=true + DEPLOY_TIER=dev ne devrait pas être bloqué"
fi

# CAS 3 : reset non défini → retour immédiat sans erreur
if bash -c "$(guard_block)" >/dev/null 2>&1; then
  ok "reset non défini → aucun effet"
else
  ko "reset non défini ne devrait pas échouer"
fi

# CAS 4 (#7647) : DEPLOY_TIER absente → refus fail-closed, même hors prod
if APP_ENV=staging RESET_TEST_DB_ONCE=true bash -c "$(guard_block)" >/dev/null 2>&1; then
  ko "staging + reset=true SANS DEPLOY_TIER devrait refuser (#7647 fail-closed)"
else
  ok "staging + reset=true sans DEPLOY_TIER → refuse de démarrer (#7647)"
fi

# CAS 5 (#7647) : DEPLOY_TIER=prod → refus catégorique
if APP_ENV=staging DEPLOY_TIER=prod RESET_TEST_DB_ONCE=true bash -c "$(guard_block)" >/dev/null 2>&1; then
  ko "staging + reset=true + DEPLOY_TIER=prod devrait refuser (#7647)"
else
  ok "staging + reset=true + DEPLOY_TIER=prod → refuse de démarrer (#7647)"
fi

# CAS 6 (#7647) : DEPLOY_TIER vide → refus (vide ≠ dev, fail-closed)
if APP_ENV=staging DEPLOY_TIER= RESET_TEST_DB_ONCE=true bash -c "$(guard_block)" >/dev/null 2>&1; then
  ko "staging + reset=true + DEPLOY_TIER vide devrait refuser (#7647)"
else
  ok "staging + reset=true + DEPLOY_TIER vide → refuse de démarrer (#7647)"
fi

echo ""
echo "==================================="
echo "Pass: ${pass}  Fail: ${fail}"
[ "${fail}" -eq 0 ]
