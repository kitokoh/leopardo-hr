#!/usr/bin/env bash
#
# PA2-OPS-008: enforce GitHub Issues as the single source of truth for the
# project backlog (replacing the old PLAN_ACTION docs-only tracking).
#
# Why: nothing verified that a PR actually referenced the GitHub Issue it
# was supposed to deliver. A PR that lands without a "Closes #<n>" (or
# Fixes/Resolves) keyword never auto-closes its issue on merge, so the
# issue stays open forever even after the work has shipped — silently
# defeating the switch to GitHub Issues as the tracking source of truth
# that this same ticket (PA2-OPS-008) is meant to cement.
#
# This check is intentionally BLOCKING (unlike PA2-AUTO-004's non-blocking
# `::warning` on missing PA2-* IDs, dev-hub/tools/check-plan-action2-pr-id.sh)
# per PA2-OPS-008 acceptance criteria #3. It reuses the same docs:/chore:
# exemption already established by PA2-AUTO-004 and documented in
# CONVENTIONS.md §4.2, so dependency bumps (dependabot: "chore(deps): ...")
# and documentation-only commits are not forced to fabricate an issue
# reference that does not exist.
#
# Usage: check-pr-closes-issue.sh <owner/repo> <pr_number>
# Requires an authenticated `gh` (GITHUB_TOKEN is enough: read pull-requests).

set -euo pipefail

REPO="${1:?usage: check-pr-closes-issue.sh <owner/repo> <pr_number>}"
PR_NUMBER="${2:?usage: check-pr-closes-issue.sh <owner/repo> <pr_number>}"

PR_JSON=$(gh api "repos/${REPO}/pulls/${PR_NUMBER}")
PR_TITLE=$(echo "$PR_JSON" | python3 -c "import json,sys; print(json.load(sys.stdin).get('title') or '')")
PR_BODY=$(echo "$PR_JSON" | python3 -c "import json,sys; print(json.load(sys.stdin).get('body') or '')")

# GitHub's own closing-keyword grammar: close/closes/closed, fix/fixes/fixed,
# resolve/resolves/resolved, followed by an optional colon and "#<number>".
CLOSES_REF=$(printf '%s\n%s' "$PR_TITLE" "$PR_BODY" \
  | grep -oiE '(close|closes|closed|fix|fixes|fixed|resolve|resolves|resolved)[[:space:]]*:?[[:space:]]*#[0-9]+' \
  | head -n1 || true)

if [[ -n "$CLOSES_REF" ]]; then
  echo "OK: PR #${PR_NUMBER} references an issue closure: '${CLOSES_REF}'"
  exit 0
fi

# Same conventional-type exemption as PA2-AUTO-004 (CONVENTIONS.md §4.2):
# feat/fix/refactor/perf/test/ci touching product code should always trace
# a backlog issue; docs:/chore: (dependency bumps, doc-only changes) do not
# necessarily have one.
CONVENTIONAL_TYPE=$(printf '%s' "$PR_TITLE" | grep -oE '^(docs|chore)(\([^)]*\))?!?:' | head -n1 || true)

if [[ -n "$CONVENTIONAL_TYPE" ]]; then
  echo "::notice::PR #${PR_NUMBER} has no 'Closes #' reference but is typed '${CONVENTIONAL_TYPE}' — exempt (docs/chore change, no backlog issue expected)."
  exit 0
fi

echo "::error::PR #${PR_NUMBER} ('${PR_TITLE}') does not reference any issue it closes. Every PR must obligatorily include 'Closes #XXX' (or 'Fixes #XXX' / 'Resolves #XXX') in its title or description, unless it is explicitly typed docs:/chore: (dependency bumps, doc-only changes). See .github/PULL_REQUEST_TEMPLATE.md and PA2-OPS-008."
exit 1
