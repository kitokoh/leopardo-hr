#!/usr/bin/env bash
#
# PA2-AUTO-004 — CI guard: flag a PR that references no PA2-* ticket ID,
# unless the PR is an explicit docs/chore change (conventional-commit
# prefix `docs:`/`docs(...)`/`chore:`/`chore(...)` in the title) or the PR
# description explicitly opts out with a `PA2: none` marker (for genuinely
# out-of-backlog changes: dependency bumps, typo fixes, CI-only tweaks with
# no dedicated ticket).
#
# Context: docs/PLAN_ACTION2/01_MODE_EXECUTION_MULTI_AGENT.md requires every
# PA2-* PR to reference its ticket ID (used downstream by the claim guard,
# PA2-AUTO-011, and by traceability tooling like the weekly report,
# PA2-AUTO-005). This complements that guard: PA2-AUTO-011 only checks *for*
# collisions once an ID is present; this check flags PRs that carry no ID at
# all instead of silently letting untracked work merge.
#
# This is intentionally a *soft* signal by default (::warning, exit 0), not
# a hard merge block: the heuristic (regex over free-form title/body text)
# will have false negatives on legitimate untracked work that forgot the
# `docs:`/`chore:` prefix or the opt-out marker. Set
# PA2_ID_CHECK_STRICT=1 to make it a hard failure (exit 1) once the team has
# verified low false-positive noise on real PRs.
#
# Usage: check-pr-has-pa2-id.sh <pr_title> <pr_body_file>
#   pr_title: PR title as a single argument (quote it).
#   pr_body_file: path to a file containing the PR body (empty file if none).

set -euo pipefail

PR_TITLE="${1:-}"
PR_BODY_FILE="${2:-}"

if [[ -z "$PR_TITLE" ]]; then
  echo "Usage: $0 <pr_title> <pr_body_file>" >&2
  exit 2
fi

PR_BODY=""
if [[ -n "$PR_BODY_FILE" && -f "$PR_BODY_FILE" ]]; then
  PR_BODY="$(cat "$PR_BODY_FILE")"
fi

COMBINED="${PR_TITLE}
${PR_BODY}"

PA2_ID=$(printf '%s\n' "$COMBINED" | grep -oE 'PA2-[A-Z0-9]+-[0-9]{3}' | head -n1 || true)

if [[ -n "$PA2_ID" ]]; then
  echo "OK: ticket ${PA2_ID} referme dans cette PR."
  exit 0
fi

# Explicit opt-out marker (docs body only, not title, to avoid accidental
# matches): "PA2: none" (case-insensitive).
if printf '%s\n' "$PR_BODY" | grep -qiE '^PA2:[[:space:]]*none[[:space:]]*$|PA2:[[:space:]]*none\b'; then
  echo "OK: opt-out explicite \"PA2: none\" trouve dans la description — hors perimetre PLAN_ACTION2 assume."
  exit 0
fi

# Conventional-commit docs/chore prefix in the title.
if printf '%s\n' "$PR_TITLE" | grep -qiE '^(docs|chore)(\([^)]*\))?[[:space:]]*:'; then
  echo "OK: PR docs/chore explicite (prefixe \"${PR_TITLE%%:*}:\") — pas d'ID PA2-* requis."
  exit 0
fi

STRICT="${PA2_ID_CHECK_STRICT:-0}"

MESSAGE="Aucun ID PA2-* (ex: PA2-OPS-011) trouve dans le titre ou la description de cette PR. Si cette PR livre un ticket PLAN_ACTION2, ajouter son ID dans le titre ou la description. Si cette PR est un changement docs/chore explicite hors backlog PA2, soit prefixer le titre par \"docs:\"/\"chore:\" (ou \"docs(scope):\"/\"chore(scope):\"), soit ajouter la ligne \"PA2: none\" dans la description."

if [[ "$STRICT" == "1" ]]; then
  echo "::error::${MESSAGE}"
  exit 1
fi

echo "::warning::${MESSAGE}"
exit 0
