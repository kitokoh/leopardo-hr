#!/usr/bin/env bash
#
# PA2-ARCH-009 — CI guard (incremental): refuse any newly added PHP file
# under api/app that is missing `declare(strict_types=1);`.
#
# Context: docs/PLAN_ACTION2/09_AUDIT_MODULES_API_STRUCTURE.md section 5 found
# `declare(strict_types=1)` (required by CONVENTIONS.md §2.1) applied 100% on
# recently-added modules but missing on 40-60% of files in older modules
# (HR, Payroll, Attendance, Cameras). The retrofit on existing files is a
# one-time mechanical fix (see CHANGELOG PA2-ARCH-009); this guard prevents
# the debt from reappearing on *new* files going forward, without requiring
# a full-repo baseline or blocking unrelated PRs on pre-existing files that
# are merely touched (only files ADDED in the diff are checked).
#
# Method: diff the base and head commit for the current PR/push, take only
# added (`A`) PHP files under api/app, and fail if any of them lacks the
# directive. Config/migration/factory files are exempt (Laravel convention:
# config/*.php returns an array and never declares strict_types; migrations
# and stubs are not consistently covered by CONVENTIONS.md's own examples),
# mirroring the "sauf config" carve-out already stated in CONVENTIONS.md.
#
# Usage: dev-hub/tools/check-strict-types-new-files.sh <base_sha> <head_sha> [api_dir]

set -euo pipefail

BASE_SHA="${1:-}"
HEAD_SHA="${2:-}"
API_DIR="${3:-api}"

if [ -z "$BASE_SHA" ] || [ -z "$HEAD_SHA" ]; then
  echo "Usage: $0 <base_sha> <head_sha> [api_dir]" >&2
  exit 1
fi

if ! git cat-file -e "${BASE_SHA}^{commit}" 2>/dev/null; then
  git fetch --no-tags --depth=1 origin "${BASE_SHA}" 2>/dev/null || true
fi
if ! git cat-file -e "${HEAD_SHA}^{commit}" 2>/dev/null; then
  git fetch --no-tags --depth=1 origin "${HEAD_SHA}" 2>/dev/null || true
fi

mapfile -t added_files < <(
  git diff --name-only --diff-filter=A "${BASE_SHA}" "${HEAD_SHA}" -- "${API_DIR}/app" \
    | grep -E '\.php$' \
    | grep -vE '/config/|/database/(migrations|seeders|factories)/' \
    | sort -u || true
)

if [ ${#added_files[@]} -eq 0 ]; then
  echo "No newly added PHP files under ${API_DIR}/app in this diff — nothing to check."
  exit 0
fi

VIOLATIONS=0

for file in "${added_files[@]}"; do
  if [ ! -f "$file" ]; then
    continue
  fi
  if ! grep -q 'declare(strict_types=1)' "$file"; then
    echo "❌ Newly added file missing declare(strict_types=1): $file"
    VIOLATIONS=$((VIOLATIONS + 1))
  fi
done

echo ""
echo "Checked ${#added_files[@]} newly added PHP file(s) under ${API_DIR}/app."

if [ "$VIOLATIONS" -gt 0 ]; then
  echo "Found $VIOLATIONS new file(s) missing declare(strict_types=1) (CONVENTIONS.md §2.1, PA2-ARCH-009)."
  exit 1
fi

echo "✅ All newly added files declare strict_types."
