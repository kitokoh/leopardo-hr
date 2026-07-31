#!/usr/bin/env bash
#
# PA2-I18N-007 — CI guard: fail a diff that introduces a new hardcoded
# French message (string literal containing an accented character) inside
# an API controller, instead of going through the `__('xxx.yyy')` catalog.
#
# Context: docs/archive/PLAN_ACTION2/02_BACKLOG_ATOMIQUE.md PA2-I18N-007 found a
# hardcoded French string in
# api/app/Modules/SmartAttendance/Interfaces/Api/V1/GeoSessionController.php
# (already fixed to use __('attendance.geo_session_approved')) plus several
# other controllers still returning raw French strings in JSON responses
# (e.g. BillingController, SelfServiceTrialController, PaySlipController,
# AttendanceModeController, GeoAttendanceController — tracked separately,
# not blocking, since retrofitting all of them is a larger effort). This
# guard only prevents the debt from growing: it looks at *added* lines in
# the diff for the current PR/push under any `*Controller.php` file and
# fails if a new line adds a quoted string literal containing an accented
# Latin character (a heuristic proxy for "hardcoded French text"), while
# ignoring comment-only lines and lines that already use the __() helper.
#
# This is intentionally a lightweight grep-based heuristic (consistent with
# the sibling guards in this directory), not a full PHP parser: it can have
# false positives (e.g. an accented character inside a *routing constant* or
# *enum* value) and false negatives (French text without any accented
# character, e.g. "Le prix"). Reviewers remain the second line of defense;
# the goal is to catch the common case (new user-facing message strings)
# cheaply in CI.
#
# Usage: dev-hub/tools/check-hardcoded-accented-messages.sh <base_sha> <head_sha> [api_dir]

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

# All Controller.php files touched (added or modified) in this diff, under
# api/app (covers both api/app/Modules/*/Interfaces and legacy
# api/app/Http/Controllers).
mapfile -t touched_files < <(
  git diff --name-only --diff-filter=ACMR "${BASE_SHA}" "${HEAD_SHA}" -- "${API_DIR}/app" \
    | grep -E 'Controller\.php$' \
    | sort -u || true
)

if [ ${#touched_files[@]} -eq 0 ]; then
  echo "No Controller.php files touched under ${API_DIR}/app in this diff — nothing to check."
  exit 0
fi

VIOLATIONS=0

for file in "${touched_files[@]}"; do
  if [ ! -f "$file" ]; then
    continue
  fi

  # Added lines only (unified diff, strip the leading '+'), skip the
  # `+++` file header line, skip pure comment lines (// or #), and skip
  # lines that already call the translation helper.
  while IFS= read -r line; do
    if [[ "$line" =~ ^\+\+\+ ]]; then
      continue
    fi
    content="${line#+}"

    trimmed="$(echo "$content" | sed -E 's/^[[:space:]]*//')"
    if [[ "$trimmed" =~ ^(//|\#|\*) ]]; then
      continue
    fi
    if echo "$content" | grep -qE "__\("; then
      continue
    fi

    # Look for a quoted string literal (single or double) containing at
    # least one accented Latin character.
    if echo "$content" | grep -qP "['\"][^'\"]*[àâäéèêëîïôöùûüçÀÂÄÉÈÊËÎÏÔÖÙÛÜÇ][^'\"]*['\"]"; then
      echo "❌ New hardcoded accented string literal in controller: $file"
      echo "   ${trimmed}"
      VIOLATIONS=$((VIOLATIONS + 1))
    fi
  done < <(git diff -U0 "${BASE_SHA}" "${HEAD_SHA}" -- "$file" | grep -E '^\+' || true)
done

echo ""
echo "Checked ${#touched_files[@]} touched Controller.php file(s) under ${API_DIR}/app."

if [ "$VIOLATIONS" -gt 0 ]; then
  echo "Found $VIOLATIONS new hardcoded accented string(s) in controllers (CONVENTIONS.md, PA2-I18N-007)."
  echo "Use __('catalog.key') (api/lang/*/*.php) instead of a hardcoded French message."
  exit 1
fi

echo "✅ No new hardcoded accented strings introduced in controllers."
