#!/usr/bin/env bash
#
# PA2-AUTO-009 — Nettoyage branches stale.
#
# Complements dev-hub/tools/repository-hygiene-report.ps1 (which only lists
# merged/unmerged branches) with:
#   1. A "stale" definition beyond "merged": remote branches (excluding
#      main/HEAD) with no commit in the last N days (default 60), whether
#      merged or not.
#   2. A controlled manual deletion path: dry-run by default (list only),
#      explicit `--delete` required to actually remove anything, and by
#      default only *merged* branches are eligible for deletion even with
#      `--delete` — deleting a stale-but-unmerged branch requires the
#      separate `--include-unmerged` flag, since that branch may still hold
#      someone's in-progress work.
#
# This never runs automatically in CI: it is a local/manual tool an agent
# or maintainer runs deliberately, consistent with
# docs/PLAN_ACTION2/01_MODE_EXECUTION_MULTI_AGENT.md (no automated force
# changes to shared branch state without a human decision).
#
# Usage:
#   dev-hub/tools/cleanup-stale-branches.sh [--days N] [--delete] [--include-unmerged] [--yes] [--no-fetch]
#
#   --days N            Staleness threshold in days since last commit (default: 60).
#   --delete            Actually delete eligible branches on origin (default: dry-run/list only).
#   --include-unmerged  Also make stale-but-unmerged branches eligible for deletion (implies extra risk).
#   --yes               Skip the per-branch confirmation prompt (still requires --delete).
#   --no-fetch          Skip `git fetch --prune origin` (assumes refs are already up to date).

set -euo pipefail

DAYS=60
DO_DELETE=0
INCLUDE_UNMERGED=0
ASSUME_YES=0
DO_FETCH=1

while [[ $# -gt 0 ]]; do
  case "$1" in
    --days)
      DAYS="${2:?--days requires a value}"
      shift 2
      ;;
    --delete)
      DO_DELETE=1
      shift
      ;;
    --include-unmerged)
      INCLUDE_UNMERGED=1
      shift
      ;;
    --yes)
      ASSUME_YES=1
      shift
      ;;
    --no-fetch)
      DO_FETCH=0
      shift
      ;;
    -h|--help)
      grep -E '^#( |$)' "$0" | sed 's/^# \{0,1\}//'
      exit 0
      ;;
    *)
      echo "Unknown argument: $1" >&2
      exit 2
      ;;
  esac
done

if ! [[ "$DAYS" =~ ^[0-9]+$ ]]; then
  echo "Invalid --days value: $DAYS (must be a non-negative integer)" >&2
  exit 2
fi

if [[ "$DO_FETCH" -eq 1 ]]; then
  git fetch --prune origin
fi

CUTOFF_EPOCH=$(( $(date +%s) - DAYS * 86400 ))

# All remote branches except main/HEAD. Note: git for-each-ref renders the
# refs/remotes/origin/HEAD symref's short name as bare "origin" (not
# "origin/HEAD"), so both forms are excluded here.
mapfile -t REMOTE_BRANCHES < <(
  git for-each-ref --format='%(refname:short)' refs/remotes/origin |
    grep -v -E '^origin$|^origin/(main|HEAD)$'
)

if [[ ${#REMOTE_BRANCHES[@]} -eq 0 ]]; then
  echo "No remote branches other than origin/main — nothing to check."
  exit 0
fi

declare -a MERGED_STALE=()
declare -a UNMERGED_STALE=()
declare -a NOT_STALE=()

for BRANCH in "${REMOTE_BRANCHES[@]}"; do
  LAST_COMMIT_EPOCH=$(git log -1 --format=%ct "$BRANCH")
  IS_MERGED=0
  if git merge-base --is-ancestor "$BRANCH" origin/main 2>/dev/null; then
    IS_MERGED=1
  fi

  if [[ "$LAST_COMMIT_EPOCH" -le "$CUTOFF_EPOCH" ]]; then
    if [[ "$IS_MERGED" -eq 1 ]]; then
      MERGED_STALE+=("$BRANCH")
    else
      UNMERGED_STALE+=("$BRANCH")
    fi
  else
    NOT_STALE+=("$BRANCH")
  fi
done

echo "# Stale branch report (threshold: ${DAYS}d, cutoff: $(date -d "@${CUTOFF_EPOCH}" -Iseconds 2>/dev/null || date -r "${CUTOFF_EPOCH}" -Iseconds))"
echo
echo "## Merged into origin/main AND stale (safe deletion candidates): ${#MERGED_STALE[@]}"
for b in "${MERGED_STALE[@]:-}"; do
  [[ -n "$b" ]] && echo "- $b (last commit: $(git log -1 --format=%cd --date=short "$b"))"
done
echo
echo "## Stale but NOT merged (deletion would drop unmerged work — review manually): ${#UNMERGED_STALE[@]}"
for b in "${UNMERGED_STALE[@]:-}"; do
  [[ -n "$b" ]] && echo "- $b (last commit: $(git log -1 --format=%cd --date=short "$b"))"
done
echo
echo "## Active (not stale): ${#NOT_STALE[@]}"
for b in "${NOT_STALE[@]:-}"; do
  [[ -n "$b" ]] && echo "- $b (last commit: $(git log -1 --format=%cd --date=short "$b"))"
done

if [[ "$DO_DELETE" -eq 0 ]]; then
  echo
  echo "Dry-run only (no --delete passed). Re-run with --delete to remove the merged+stale branches listed above from origin."
  exit 0
fi

DELETE_TARGETS=("${MERGED_STALE[@]:-}")
if [[ "$INCLUDE_UNMERGED" -eq 1 ]]; then
  DELETE_TARGETS+=("${UNMERGED_STALE[@]:-}")
fi

# Drop any empty entries from possible unset array expansion above.
FILTERED_TARGETS=()
for t in "${DELETE_TARGETS[@]:-}"; do
  [[ -n "$t" ]] && FILTERED_TARGETS+=("$t")
done

if [[ ${#FILTERED_TARGETS[@]} -eq 0 ]]; then
  echo
  echo "Nothing eligible for deletion (pass --include-unmerged to also consider stale-but-unmerged branches)."
  exit 0
fi

echo
echo "About to delete ${#FILTERED_TARGETS[@]} branch(es) on origin:"
for t in "${FILTERED_TARGETS[@]}"; do
  echo "- $t"
done

if [[ "$ASSUME_YES" -ne 1 ]]; then
  read -r -p "Confirm deletion of the branches listed above on origin? [y/N] " CONFIRM
  if [[ ! "$CONFIRM" =~ ^[Yy]$ ]]; then
    echo "Aborted, no branch deleted."
    exit 1
  fi
fi

for t in "${FILTERED_TARGETS[@]}"; do
  LOCAL_NAME="${t#origin/}"
  echo "Deleting origin/${LOCAL_NAME}..."
  git push origin --delete "$LOCAL_NAME"
done

echo "Done: ${#FILTERED_TARGETS[@]} branch(es) deleted on origin."
