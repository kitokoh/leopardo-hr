#!/usr/bin/env bash
# check-bc-imports-test.sh — Auto-test de la garde d'imports BC (MAT-002, #5860)
#
# Dépôt factice : deux BC (BC-ALPHA possédant Modules/Alpha, BC-BETA possédant
# Core/Beta). Vérifie :
#   - import dans le contrat (BC-BETA déclaré par BC-ALPHA) → exit 0
#   - import hors contrat (BC-BETA non déclaré)           → exit 1
#   - registre absent                                     → exit 1
#
# Usage : bash dev-hub/tools/check-bc-imports-test.sh

set -euo pipefail

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
GUARD="$HERE/check-bc-imports.sh"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

REPO_ROOT="$(cd "$HERE/../.." && pwd)"

mkdir -p "$TMP/api/app/Modules/Alpha/Domain"
mkdir -p "$TMP/api/app/Core/Beta/Domain"
mkdir -p "$TMP/docs/architecture"

TOTAL=0
PASS=0

expect_exit() { # expected_code, label
  local expected="$1" label="$2" actual=0
  TOTAL=$((TOTAL + 1))
  set +e
  bash "$GUARD" "$TMP" > /dev/null 2>&1
  actual=$?
  set -e
  if [[ "$actual" -eq "$expected" ]]; then
    PASS=$((PASS + 1))
    echo "  ✅  $label (exit $actual)"
  else
    echo "  ❌  $label — attendu exit $expected, obtenu $actual"
    return 1
  fi
}

REGISTRY='{
  "version": "1.0",
  "updated": "2026-08-28",
  "issue": 5860,
  "contexts": [
    {
      "id": "01",
      "code": "BC-ALPHA",
      "name": "Alpha",
      "owner": "kitokoh",
      "priority": "P0",
      "status": "existing",
      "paths": ["api/app/Modules/Alpha"],
      "dependencies": ["BC-BETA"]
    },
    {
      "id": "02",
      "code": "BC-BETA",
      "name": "Beta",
      "owner": "kitokoh",
      "priority": "P1",
      "status": "existing",
      "paths": ["api/app/Core/Beta"],
      "dependencies": []
    }
  ],
  "sharedPaths": []
}'

echo "── check-bc-imports.sh — tests ──"

# 1. Import autorisé par le contrat → 0
printf '%s' "$REGISTRY" > "$TMP/docs/architecture/bounded-context-registry.json"
cat > "$TMP/api/app/Modules/Alpha/Domain/AlphaModel.php" << 'PHP'
<?php
namespace App\Modules\Alpha\Domain;

use App\Core\Beta\Domain\BetaModel;

class AlphaModel {}
PHP
cat > "$TMP/api/app/Core/Beta/Domain/BetaModel.php" << 'PHP'
<?php
namespace App\Core\Beta\Domain;

class BetaModel {}
PHP
expect_exit 0 "import dans le contrat (BC-BETA déclaré)"

# 2. Import hors contrat → 1
sed -i 's/"dependencies": \["BC-BETA"\]/"dependencies": []/' "$TMP/docs/architecture/bounded-context-registry.json"
expect_exit 1 "import hors contrat (BC-BETA non déclaré)"

# 3. Registre absent → 1
rm "$TMP/docs/architecture/bounded-context-registry.json"
expect_exit 1 "registre absent"

# ── Bilan ───────────────────────────────────────────────────────────────────
echo ""
if [[ "$PASS" -eq "$TOTAL" ]]; then
  echo "✅  check-bc-imports-test.sh — $PASS/$TOTAL tests OK"
else
  echo "❌  check-bc-imports-test.sh — $PASS/$TOTAL tests OK"
  exit 1
fi
