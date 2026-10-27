#!/usr/bin/env bash
#
# check-tests-no-frozen-dates.test.sh — tests du garde (issue #8396).
#
# Scénarios :
#   1. date FUTURE → vert (elle n'est pas encore une bombe à retardement) ;
#   2. date PASSÉE → rouge (mentionne le fichier et la ligne) ;
#   3. date passée + Carbon::setTestNow() dans le fichier → vert (horloge maîtrisée) ;
#   4. date passée + marqueur @frozen-date-ok → vert (exemption explicite) ;
#   5. date relative à now() → vert.
#
# Usage : bash dev-hub/tools/tests/check-tests-no-frozen-dates.test.sh
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
GUARD="${SCRIPT_DIR}/../check-tests-no-frozen-dates.sh"
TMP="$(mktemp -d)"
trap 'rm -rf "${TMP}"' EXIT

pass=0
fail=0

# Le garde est DIFF-BASED : on construit un dépôt jetable avec un commit de
# base, puis on juge le commit suivant.
setup_repo() {
  local dir="$1"
  mkdir -p "${dir}/api/tests/Feature"
  cd "${dir}"
  git init -q
  git config user.email "test@example.com"
  git config user.name "test"
  echo "<?php" > "api/tests/Feature/Base.php"
  git add -A
  git commit -q -m "base"
}

run_guard() {
  local dir="$1"
  cd "${dir}"
  GUARD_ROOT="${dir}" \
    GITHUB_BASE_SHA="$(git rev-parse HEAD~1)" GITHUB_HEAD_SHA="$(git rev-parse HEAD)" \
    bash "${GUARD}" 2>&1 || true
}

# ── 1. Date FUTURE → vert ────────────────────────────────────────────────────
D="${TMP}/future"
setup_repo "${D}"
cat > "${D}/api/tests/Feature/FutureTest.php" << 'PHEOF'
<?php

$future = '2099-12-31';
PHEOF
cd "${D}" && git add -A && git commit -q -m "future"
out="$(run_guard "${D}")"
if [[ "${out}" == *"✓"* ]]; then
  echo "✅ date future → vert"
  pass=$((pass + 1))
else
  echo "❌ date future → vert attendu"
  echo "${out}" | tail -4
  fail=$((fail + 1))
fi

# ── 2. Date PASSÉE → rouge ───────────────────────────────────────────────────
D="${TMP}/past"
setup_repo "${D}"
cat > "${D}/api/tests/Feature/PastTest.php" << 'PHEOF'
<?php

$overdue = '2020-01-15';
PHEOF
cd "${D}" && git add -A && git commit -q -m "past"
out="$(run_guard "${D}")"
if [[ "${out}" == *"::error"* ]] && [[ "${out}" == *"2020-01-15"* ]]; then
  echo "✅ date passée → rouge avec la date"
  pass=$((pass + 1))
else
  echo "❌ date passée → rouge attendu"
  echo "${out}" | tail -4
  fail=$((fail + 1))
fi

# ── 3. Date passée + horloge maîtrisée → vert ────────────────────────────────
D="${TMP}/clock"
setup_repo "${D}"
cat > "${D}/api/tests/Feature/ClockTest.php" << 'PHEOF'
<?php

use Illuminate\Support\Carbon;

Carbon::setTestNow('2026-01-01');
$overdue = '2020-01-15';
PHEOF
cd "${D}" && git add -A && git commit -q -m "clock"
out="$(run_guard "${D}")"
if [[ "${out}" == *"✓"* ]]; then
  echo "✅ horloge maîtrisée → vert"
  pass=$((pass + 1))
else
  echo "❌ horloge maîtrisée → vert attendu"
  echo "${out}" | tail -4
  fail=$((fail + 1))
fi

# ── 4. Date passée + marqueur d'exemption → vert ─────────────────────────────
D="${TMP}/exempt"
setup_repo "${D}"
cat > "${D}/api/tests/Feature/ExemptTest.php" << 'PHEOF'
<?php

// @frozen-date-ok — import d'historique comptable
$legacy = '2019-06-30';
PHEOF
cd "${D}" && git add -A && git commit -q -m "exempt"
out="$(run_guard "${D}")"
if [[ "${out}" == *"✓"* ]]; then
  echo "✅ marqueur d'exemption → vert"
  pass=$((pass + 1))
else
  echo "❌ marqueur d'exemption → vert attendu"
  echo "${out}" | tail -4
  fail=$((fail + 1))
fi

# ── 5. Date relative à now() → vert ──────────────────────────────────────────
D="${TMP}/relative"
setup_repo "${D}"
cat > "${D}/api/tests/Feature/RelativeTest.php" << 'PHEOF'
<?php

use Illuminate\Support\Carbon;

$overdue = Carbon::now()->subDays(30)->toDateString();
PHEOF
cd "${D}" && git add -A && git commit -q -m "relative"
out="$(run_guard "${D}")"
if [[ "${out}" == *"✓"* ]]; then
  echo "✅ date relative → vert"
  pass=$((pass + 1))
else
  echo "❌ date relative → vert attendu"
  echo "${out}" | tail -4
  fail=$((fail + 1))
fi

echo ""
echo "── check-tests-no-frozen-dates.test.sh : ${pass} passés, ${fail} échecs ──"
[[ "${fail}" -eq 0 ]]
