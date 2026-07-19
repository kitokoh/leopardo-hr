#!/usr/bin/env bash
#
# PA2-ARCH-007 — CI guard: fail if a module Interfaces controller is never
# referenced from any routes file (dead code / duplicate migration residue).
#
# Context: docs/PLAN_ACTION2/09_AUDIT_MODULES_API_STRUCTURE.md section 2 found
# 4 controllers fully duplicated across two modules where only one copy was
# actually wired into routes/ — the "logical" copy silently received zero
# traffic. This script makes that class of bug fail CI instead of rotting.
#
# Method: for every `*Controller.php` under api/app/Modules/*/Interfaces/,
# resolve its FQCN (namespace + class name) and grep for that FQCN across
# api/routes/**.php and api/app/Modules/*/routes/*.php. A controller is
# considered wired if its FQCN (backslash form) appears anywhere in a route
# file — whether via a `use` import + short name, or a fully-qualified
# reference.
#
# Usage: dev-hub/tools/check-unrouted-controllers.sh [api_dir]

set -euo pipefail

API_DIR="${1:-api}"
MODULES_DIR="$API_DIR/app/Modules"

if [ ! -d "$MODULES_DIR" ]; then
  echo "❌ Modules directory not found: $MODULES_DIR" >&2
  exit 1
fi

# Collect all route files: root routes/ plus per-module routes/ directories.
ROUTE_FILES=$(find "$API_DIR/routes" "$MODULES_DIR" -type f -name "*.php" -path "*routes*" 2>/dev/null | sort)

if [ -z "$ROUTE_FILES" ]; then
  echo "❌ No route files discovered under $API_DIR/routes or $MODULES_DIR/*/routes." >&2
  exit 1
fi

ORPHANS=0
CHECKED=0

while IFS= read -r -d '' controller_file; do
  CHECKED=$((CHECKED + 1))

  namespace=$(grep -m1 -oP '^namespace\s+\K[^;]+' "$controller_file" || true)
  classname=$(basename "$controller_file" .php)

  if [ -z "$namespace" ]; then
    echo "⚠️  Skipping (no namespace declaration): $controller_file"
    continue
  fi

  fqcn="${namespace}\\${classname}"

  found=0
  for route_file in $ROUTE_FILES; do
    if grep -qF "$fqcn" "$route_file"; then
      found=1
      break
    fi
  done

  if [ "$found" -eq 0 ]; then
    echo "❌ Orphan controller (never referenced in any routes/ file): $fqcn ($controller_file)"
    ORPHANS=$((ORPHANS + 1))
  fi
done < <(find "$MODULES_DIR" -type f -path "*/Interfaces/*" -iname "*Controller.php" -print0 | sort -z)

echo ""
echo "Checked $CHECKED controllers under $MODULES_DIR/*/Interfaces/."

if [ "$ORPHANS" -gt 0 ]; then
  echo "Found $ORPHANS orphan controller(s) never wired into routes/."
  echo "Either wire it into a routes file, or delete it (see PA2-ARCH-007)."
  exit 1
fi

echo "✅ No orphan controllers found."
