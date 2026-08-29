#!/usr/bin/env bash
#
# check-duplicate-use-imports.test.sh — tests du garde (issue #5519).
#
# Scénarios :
#   1. fixture propre → vert ;
#   2. fixture avec double `use` → rouge (mentionne le symbole et la ligne) ;
#   3. deux imports différents avec le même alias `as Y` → rouge.
#
# Usage : bash dev-hub/tools/tests/check-duplicate-use-imports.test.sh
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
GUARD="${SCRIPT_DIR}/../check-duplicate-use-imports.sh"
TMP="$(mktemp -d)"
trap 'rm -rf "${TMP}"' EXIT

pass=0
fail=0

# ── 1. Fixture propre → vert ─────────────────────────────────────────────────
FIX="${TMP}/clean"
mkdir -p "${FIX}/routes/modules" "${FIX}/app/Providers"
cat > "${FIX}/routes/modules/rh.php" << 'PHEOF'
<?php

use App\Modules\HR\Interfaces\Api\V1\Controllers\EmployeeController;
use Illuminate\Support\Facades\Route;

Route::get('/employees', [EmployeeController::class, 'index']);
PHEOF
cat > "${FIX}/app/Providers/AppServiceProvider.php" << 'PHEOF'
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider {}
PHEOF
out="$(bash "${GUARD}" "${FIX}" 2>&1 || true)"
if [[ "${out}" == *"✅"* ]]; then
  echo "✅ fixture propre → vert"
  pass=$((pass + 1))
else
  echo "❌ fixture propre → vert attendu"
  echo "${out}" | tail -4
  fail=$((fail + 1))
fi

# ── 2. Double `use` → rouge ──────────────────────────────────────────────────
FIX="${TMP}/dup"
mkdir -p "${FIX}/routes/modules"
cat > "${FIX}/routes/modules/geo.php" << 'PHEOF'
<?php

use App\Modules\Attendance\Interfaces\Api\V1\AttendanceDayClosureController;
use App\Modules\Attendance\Interfaces\Api\V1\AttendanceDayClosureController;
use Illuminate\Support\Facades\Route;

Route::get('/day-closures', [AttendanceDayClosureController::class, 'index']);
PHEOF
out="$(bash "${GUARD}" "${FIX}" 2>&1 || true)"
if [[ "${out}" == *"::error::"* ]] && [[ "${out}" == *"AttendanceDayClosureController"* ]]; then
  echo "✅ double use → rouge avec le symbole"
  pass=$((pass + 1))
else
  echo "❌ double use → rouge attendu"
  echo "${out}" | tail -4
  fail=$((fail + 1))
fi

# ── 3. Même alias `as Y` → rouge ─────────────────────────────────────────────
FIX="${TMP}/alias"
mkdir -p "${FIX}/routes/modules"
cat > "${FIX}/routes/modules/mix.php" << 'PHEOF'
<?php

use App\Modules\HR\Domain\Models\Employee as User;
use App\Core\Auth\Domain\Models\Employee as User;
use Illuminate\Support\Facades\Route;

Route::get('/mix', fn () => 'ok');
PHEOF
out="$(bash "${GUARD}" "${FIX}" 2>&1 || true)"
if [[ "${out}" == *"::error::"* ]] && [[ "${out}" == *"User"* ]]; then
  echo "✅ alias dupliqué → rouge"
  pass=$((pass + 1))
else
  echo "❌ alias dupliqué → rouge attendu"
  echo "${out}" | tail -4
  fail=$((fail + 1))
fi

echo ""
echo "── check-duplicate-use-imports.test.sh : ${pass} passés, ${fail} échecs ──"
[[ "${fail}" -eq 0 ]]
