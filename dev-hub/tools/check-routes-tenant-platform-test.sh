#!/usr/bin/env bash
# check-routes-tenant-platform-test.sh — Auto-test de la garde routes tenant/platform (MAT-003, #5861)
#
# Dépôt factice avec trois routes :
#   - route tenant propre            → exit 0
#   - R1 : route platform + tenant   → exit 1
#   - R2 : route tenant → PlatformAdminController → exit 1
#   - R3 : sanctum sans tenant → contrôleur module tenant → exit 1
#
# Usage : bash dev-hub/tools/check-routes-tenant-platform-test.sh

set -euo pipefail

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
GUARD="$HERE/check-routes-tenant-platform.sh"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

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

mkdir -p "$TMP/api/routes/modules"

cat > "$TMP/api/routes/api.php" << 'PHP'
<?php

use App\Modules\HR\Interfaces\Api\V1\Controllers\EmployeeController;
use App\Modules\Platform\Interfaces\Api\V1\Controllers\PlatformAdminDashboardController;

Route::prefix('v1')->group(function (): void {
    // Route tenant propre : sanctum + tenant sur contrôleur de module tenant
    Route::get('/employees', [EmployeeController::class, 'index'])
        ->middleware(['auth:sanctum', 'tenant']);

    // R2 : route tenant vers contrôleur strictement platform-admin
    Route::get('/bad-tenant', [PlatformAdminDashboardController::class, 'stats'])
        ->middleware(['auth:sanctum', 'tenant']);

    // R3 : sanctum sans tenant sur contrôleur de module tenant
    Route::get('/leaky', [EmployeeController::class, 'show'])
        ->middleware(['auth:sanctum']);

    // R1 : route platform avec middleware tenant
    Route::middleware(['auth:super_admin_api', 'tenant'])->prefix('admin')->group(function (): void {
        Route::get('/stats', [PlatformAdminDashboardController::class, 'stats']);
    });
});
PHP

echo "── check-routes-tenant-platform.sh — tests ──"

# 1. Route tenant propre uniquement → 0
cat > "$TMP/api/routes/api.php" << 'PHP'
<?php

use App\Modules\HR\Interfaces\Api\V1\Controllers\EmployeeController;

Route::get('/employees', [EmployeeController::class, 'index'])
    ->middleware(['auth:sanctum', 'tenant']);
PHP
expect_exit 0 "route tenant propre (sanctum + tenant)"

# 2. R1 : platform + tenant → 1
cat > "$TMP/api/routes/api.php" << 'PHP'
<?php

use App\Modules\Platform\Interfaces\Api\V1\Controllers\PlatformAdminDashboardController;

Route::middleware(['auth:super_admin_api', 'tenant'])->prefix('admin')->group(function (): void {
    Route::get('/stats', [PlatformAdminDashboardController::class, 'stats']);
});
PHP
expect_exit 1 "R1 — route platform avec middleware tenant"

# 3. R2 : tenant → PlatformAdminController → 1
cat > "$TMP/api/routes/api.php" << 'PHP'
<?php

use App\Modules\HR\Interfaces\Api\V1\Controllers\EmployeeController;
use App\Modules\Platform\Interfaces\Api\V1\Controllers\PlatformAdminDashboardController;

Route::get('/employees', [EmployeeController::class, 'index'])
    ->middleware(['auth:sanctum', 'tenant']);
Route::get('/bad-tenant', [PlatformAdminDashboardController::class, 'stats'])
    ->middleware(['auth:sanctum', 'tenant']);
PHP
expect_exit 1 "R2 — route tenant vers contrôleur platform-admin"

# 4. R3 : sanctum sans tenant sur module tenant → 1
cat > "$TMP/api/routes/api.php" << 'PHP'
<?php

use App\Modules\HR\Interfaces\Api\V1\Controllers\EmployeeController;

Route::get('/leaky', [EmployeeController::class, 'show'])
    ->middleware(['auth:sanctum']);
PHP
expect_exit 1 "R3 — contrôleur tenant sans middleware tenant"

# ── Bilan ───────────────────────────────────────────────────────────────────
echo ""
if [[ "$PASS" -eq "$TOTAL" ]]; then
  echo "✅  check-routes-tenant-platform-test.sh — $PASS/$TOTAL tests OK"
else
  echo "❌  check-routes-tenant-platform-test.sh — $PASS/$TOTAL tests OK"
  exit 1
fi
