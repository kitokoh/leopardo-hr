#!/usr/bin/env bash
# check-bc-registry-test.sh — Auto-test de la garde du registre BC (MAT-001, #5859)
#
# Construit un dépôt factice dans un répertoire temporaire et vérifie que
# check-bc-registry.sh échoue exactement quand il le faut :
#   - registre valide + chemins réels          → exit 0
#   - chemin existing absent                   → exit 1
#   - module orphelin (non réclamé)            → exit 1
#   - propriétaire absent de CODEOWNERS        → exit 1
#   - JSON invalide                            → exit 1
#
# Usage : bash dev-hub/tools/check-bc-registry-test.sh

set -euo pipefail

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
GUARD="$HERE/check-bc-registry.sh"
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

# ── Structure du dépôt factice ──────────────────────────────────────────────
mkdir -p "$TMP/api/app/Modules/Foo"
mkdir -p "$TMP/api/app/Core/Bar"
mkdir -p "$TMP/docs/architecture"
printf '# CODEOWNERS test\n* @kitokoh\n/api/ @kitokoh\n' > "$TMP/CODEOWNERS"

# Le schéma est référencé par la garde : on le copie depuis le dépôt réel.
REPO_ROOT="$(cd "$HERE/../.." && pwd)"
cp "$REPO_ROOT/docs/architecture/bounded-context-registry.schema.json" "$TMP/docs/architecture/"

write_registry() { # json_payload
  printf '%s' "$1" > "$TMP/docs/architecture/bounded-context-registry.json"
}

VALID=$(cat << 'JSON'
{
  "version": "1.0",
  "updated": "2026-08-28",
  "issue": 5859,
  "contexts": [
    {
      "id": "01",
      "code": "BC-TEST",
      "name": "Test Context",
      "owner": "kitokoh",
      "priority": "P0",
      "status": "existing",
      "paths": ["api/app/Modules/Foo"],
      "dependencies": ["BC-SHARED"]
    },
    {
      "id": "02",
      "code": "BC-SHARED",
      "name": "Shared Test",
      "owner": "kitokoh",
      "priority": "P1",
      "status": "existing",
      "paths": ["api/app/Core/Bar"],
      "dependencies": []
    }
  ],
  "sharedPaths": []
}
JSON
)

echo "── check-bc-registry.sh — tests ──"

# 1. Registre valide → 0
write_registry "$VALID"
expect_exit 0 "registre valide + chemins réels"

# 2. Chemin existing absent → 1
BROKEN_PATH=$(printf '%s' "$VALID" | sed 's|api/app/Modules/Foo|api/app/Modules/Gone|')
write_registry "$BROKEN_PATH"
expect_exit 1 "chemin existing absent"

# 3. Module orphelin → 1
mkdir -p "$TMP/api/app/Modules/Orphan"
write_registry "$VALID"
expect_exit 1 "module orphelin non réclamé"
rmdir "$TMP/api/app/Modules/Orphan"

# 4. Propriétaire absent de CODEOWNERS → 1
NO_OWNER=$(printf '%s' "$VALID" | sed 's/"owner": "kitokoh"/"owner": "ghost"/')
write_registry "$NO_OWNER"
expect_exit 1 "propriétaire absent de CODEOWNERS"

# 5. JSON invalide → 1
printf '{ not json' > "$TMP/docs/architecture/bounded-context-registry.json"
expect_exit 1 "JSON invalide"

# ── Bilan ───────────────────────────────────────────────────────────────────
echo ""
if [[ "$PASS" -eq "$TOTAL" ]]; then
  echo "✅  check-bc-registry-test.sh — $PASS/$TOTAL tests OK"
else
  echo "❌  check-bc-registry-test.sh — $PASS/$TOTAL tests OK"
  exit 1
fi
