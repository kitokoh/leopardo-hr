#!/usr/bin/env bash
#
# check-providers-syntax.test.sh — tests du garde (issue #5519).
#
# Scénarios (via PHP_BIN stub, aucun PHP réel requis) :
#   1. tous les providers valides → vert ;
#   2. un provider avec erreur de syntaxe → rouge (nom du fichier cité).
#
# Usage : bash dev-hub/tools/tests/check-providers-syntax.test.sh
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
GUARD="${SCRIPT_DIR}/../check-providers-syntax.sh"
TMP="$(mktemp -d)"
trap 'rm -rf "${TMP}"' EXIT

pass=0
fail=0

FIX="${TMP}/providers"
mkdir -p "${FIX}/app/Providers" "${FIX}/app/Modules/HR/Providers"
cat > "${FIX}/app/Providers/AppServiceProvider.php" << 'PHEOF'
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider {}
PHEOF
cat > "${FIX}/app/Modules/HR/Providers/HRServiceProvider.php" << 'PHEOF'
<?php

namespace App\Modules\HR\Providers;

use Illuminate\Support\ServiceProvider;

class HRServiceProvider extends ServiceProvider {}
PHEOF

# ── 1. Tous valides → vert ───────────────────────────────────────────────────
cat > "${TMP}/php-ok.sh" << 'PHEOF'
#!/bin/sh
exit 0
PHEOF
chmod +x "${TMP}/php-ok.sh"
out="$(PHP_BIN="${TMP}/php-ok.sh" bash "${GUARD}" "${FIX}" 2>&1 || true)"
if [[ "${out}" == *"✅"* ]]; then
  echo "✅ providers valides → vert"
  pass=$((pass + 1))
else
  echo "❌ providers valides → vert attendu"
  echo "${out}" | tail -4
  fail=$((fail + 1))
fi

# ── 2. Provider corrompu → rouge ─────────────────────────────────────────────
cat > "${TMP}/php-bad.sh" << 'PHEOF'
#!/bin/sh
case "$1" in
  -l)
    case "$2" in
      *AppServiceProvider.php) exit 1 ;;
      *) exit 0 ;;
    esac
    ;;
  *) exit 0 ;;
esac
PHEOF
chmod +x "${TMP}/php-bad.sh"
out="$(PHP_BIN="${TMP}/php-bad.sh" bash "${GUARD}" "${FIX}" 2>&1 || true)"
if [[ "${out}" == *"::error::"* ]] && [[ "${out}" == *"AppServiceProvider"* ]]; then
  echo "✅ provider corrompu → rouge avec le fichier"
  pass=$((pass + 1))
else
  echo "❌ provider corrompu → rouge attendu"
  echo "${out}" | tail -4
  fail=$((fail + 1))
fi

echo ""
echo "── check-providers-syntax.test.sh : ${pass} passés, ${fail} échecs ──"
[[ "${fail}" -eq 0 ]]
