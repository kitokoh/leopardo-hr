#!/usr/bin/env bash
#
# #5448 — PHPStan Strict baseline debt: metric + « 0 nouvelle entrée » guard.
#
# The strict baseline (api/phpstan-strict-baseline.neon, ~8k lines, level 8) is
# the visible debt ledger of the backend. A huge baseline hides real errors
# (ADR-0016 Phase 5 surfaced 8 masked errors the day files moved out of
# baseline coverage). This tool does two things:
#
#   1. report [api_dir]                          — per-module debt report
#        (entries, occurrences, files, share of total, error-class breakdown)
#        so reduction work can be scheduled biggest-contributor-first.
#   2. guard <base_sha> <head_sha> [api_dir]     — CI guard « 0 nouvelle entrée »
#        Fails any PR that adds a new baseline entry OR increases an existing
#        entry's count, regardless of which module it touches. Reduction
#        (removal / count decrease) always passes. This is a GLOBAL ratchet,
#        complementary to the module-scoped PA2-ARCH-005 ratchet
#        (check-phpstan-baseline-delta.sh): a PR that adds debt to a module it
#        does not otherwise touch would pass PA2-ARCH-005 but fails here.
#
# Baseline files guarded (all three, same family as PA2-ARCH-005):
#   - phpstan-strict-baseline.neon   (level 8, app/Core|Modules|Shared — #5448)
#   - phpstan-baseline.neon          (legacy, app/Http|Services)
#   - phpstan-modules-baseline.neon  (legacy modules)
#
# Usage:
#   dev-hub/tools/check-phpstan-baseline-debt.sh report [api_dir]
#   dev-hub/tools/check-phpstan-baseline-debt.sh guard <base_sha> <head_sha> [api_dir]

set -euo pipefail

MODE="${1:-report}"

BASELINE_FILES=(
  "phpstan-strict-baseline.neon"
  "phpstan-baseline.neon"
  "phpstan-modules-baseline.neon"
)

PY_PARSE='import re, sys
content = sys.stdin.read()
blocks = re.findall(
    r"message:\s*\x27([^\x27]*)\x27\s*\n\s*identifier:\s*([^\s]+)\s*\n\s*count:\s*(\d+)\s*\n\s*path:\s*(\S+)",
    content,
)
for message, identifier, count, path in blocks:
    print(f"{message}\t{identifier}\t{count}\t{path}")
'

PY_MODULE='import re, sys
path = sys.stdin.read().strip()
m = re.match(r"^app/(Core|Modules)/([^/]+)/", path)
if m:
    print(f"app/{m.group(1)}/{m.group(2)}")
elif path.startswith("app/Shared/") or path == "app/Shared":
    print("app/Shared")
elif path.startswith("tests/"):
    top = path.split("/")[1] if len(path.split("/")) > 1 else path
    print(f"tests/{top}")
elif path.startswith("app/"):
    top = path.split("/")[1] if len(path.split("/")) > 1 else path
    print(f"app/{top}")
elif path.startswith("database/"):
    print("database")
elif path.startswith("routes/"):
    print("routes")
else:
    print("(hors app)")
'

parse_entries() {
  python3 -c "$PY_PARSE"
}

module_of() {
  python3 -c "$PY_MODULE"
}

# ---------------------------------------------------------------------------
# Mode report — per-module debt table.
# ---------------------------------------------------------------------------
do_report() {
  local grand_total=0
  local grand_entries=0
  local grand_files=0
  local api_dir="${1:-api}"

  echo "== PHPStan baseline debt report (#5448) =="
  echo "Baseline files: ${BASELINE_FILES[*]}"
  echo ""

  for bf in "${BASELINE_FILES[@]}"; do
    local file="$api_dir/$bf"
    if [ ! -f "$file" ]; then
      echo "⚠  $bf absent — skipped"
      continue
    fi

    local total=0
    local entries=0
    declare -A mod_occ=()
    declare -A mod_ent=()
    declare -A id_occ=()
    declare -A file_occ=()

    while IFS=$'\t' read -r message identifier count path; do
      [ -z "$path" ] && continue
      total=$((total + count))
      entries=$((entries + 1))
      mod="$(printf '%s' "$path" | module_of)"
      mod_occ["$mod"]=$(( ${mod_occ["$mod"]:-0} + count ))
      mod_ent["$mod"]=$(( ${mod_ent["$mod"]:-0} + 1 ))
      id_occ["$identifier"]=$(( ${id_occ["$identifier"]:-0} + count ))
      file_occ["$path"]=$(( ${file_occ["$path"]:-0} + count ))
    done < <(parse_entries < "$file")

    local files="${#file_occ[@]}"
    grand_total=$((grand_total + total))
    grand_entries=$((grand_entries + entries))
    grand_files=$((grand_files + files))

    echo "--- $bf ($entries entrées, $total occurrences, $files fichiers) ---"
    echo "  Dette par module (tri décroissant d'occurrences) :"
    for mod in "${!mod_occ[@]}"; do
      printf "%s\t%s\t%s\n" "$mod" "${mod_occ[$mod]}" "${mod_ent[$mod]}"
    done | sort -t $'\t' -k2,2nr | awk -F'\t' '{ printf "    %-40s %6d occ  %5d entrées\n", $1, $2, $3 }'
    echo "  Dette par classe d'erreur (top 10) :"
    for id in "${!id_occ[@]}"; do
      printf "%s\t%s\n" "$id" "${id_occ[$id]}"
    done | sort -t $'\t' -k2,2nr | head -10 | awk -F'\t' '{ printf "    %-40s %6d occ\n", $1, $2 }'
    echo ""
  done

  echo "== TOTAL (3 baselines) : $grand_entries entrées, $grand_total occurrences, $grand_files fichiers =="
  echo ""
  echo "Cible #5448 : -20 % d'entrées en 4 semaines puis -10 %/mois."
  echo "Garde active : aucune PR ne peut ajouter une entrée baseline ni augmenter un count."
}

# ---------------------------------------------------------------------------
# Mode guard — 0 nouvelle entrée.
# ---------------------------------------------------------------------------
do_guard() {
  local base_sha="${1:-}"
  local head_sha="${2:-}"
  local api_dir="${3:-api}"

  if [ -z "$base_sha" ] || [ -z "$head_sha" ]; then
    echo "Usage: $0 guard <base_sha> <head_sha> [api_dir]" >&2
    exit 2
  fi

  if ! git cat-file -e "${base_sha}^{commit}" 2>/dev/null; then
    git fetch --no-tags --depth=1 origin "${base_sha}" 2>/dev/null || true
  fi
  if ! git cat-file -e "${head_sha}^{commit}" 2>/dev/null; then
    git fetch --no-tags --depth=1 origin "${head_sha}" 2>/dev/null || true
  fi

  local violations=0

  for bf in "${BASELINE_FILES[@]}"; do
    local base_entries head_entries
    base_entries="$(mktemp)"
    head_entries="$(mktemp)"

    git show "${base_sha}:${api_dir}/${bf}" 2>/dev/null | parse_entries > "$base_entries" || true
    git show "${head_sha}:${api_dir}/${bf}" 2>/dev/null | parse_entries > "$head_entries" || true

    if [ ! -s "$base_entries" ] && [ ! -s "$head_entries" ]; then
      rm -f "$base_entries" "$head_entries"
      continue
    fi

    # key = message|identifier|path ; value = count
    declare -A base_counts=()
    while IFS=$'\t' read -r message identifier count path; do
      [ -z "$path" ] && continue
      base_counts["$message|$identifier|$path"]=$count
    done < "$base_entries"

    local file_violations=0
    while IFS=$'\t' read -r message identifier count path; do
      [ -z "$path" ] && continue
      local key="$message|$identifier|$path"
      local base_count="${base_counts[$key]:-0}"
      if [ "$base_count" -eq 0 ]; then
        echo "❌ $bf : NOUVELLE entrée baseline — $path ($identifier, count $count)"
        file_violations=$((file_violations + 1))
      elif [ "$count" -gt "$base_count" ]; then
        echo "❌ $bf : count augmenté ($base_count → $count) — $path ($identifier)"
        file_violations=$((file_violations + 1))
      fi
    done < "$head_entries"

    if [ "$file_violations" -eq 0 ]; then
      local base_total head_total
      base_total="$(awk -F'\t' '{s+=$3} END {print s+0}' "$base_entries")"
      head_total="$(awk -F'\t' '{s+=$3} END {print s+0}' "$head_entries")"
      echo "✅ $bf : 0 nouvelle entrée (occurrences $base_total → $head_total)"
    fi

    violations=$((violations + file_violations))
    rm -f "$base_entries" "$head_entries"
  done

  echo ""
  if [ "$violations" -gt 0 ]; then
    echo "❌ Garde #5448 : $violations violation(s) — une PR ne doit JAMAIS ajouter de dette baseline. Corrigez le code au lieu de re-baseliner."
    exit 1
  fi
  echo "✅ Garde #5448 : aucune nouvelle entrée baseline sur ce diff."
}

case "$MODE" in
  report)
    do_report "${2:-api}"
    ;;
  guard)
    do_guard "${2:-}" "${3:-}" "${4:-api}"
    ;;
  *)
    echo "Usage: $0 {report|guard} ..." >&2
    exit 2
    ;;
esac
