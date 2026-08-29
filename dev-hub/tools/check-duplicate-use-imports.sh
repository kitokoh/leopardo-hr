#!/usr/bin/env bash
#
# check-duplicate-use-imports.sh — garde CI « doubles imports » (issue #5519).
#
# Détecte les `use X` dupliqués dans les fichiers de routes et les Providers :
# `Cannot use ... because the name is already in use` casse le bootstrap des
# routes silencieusement (régression merge #5406, corrigée dans #5504).
#
# Périmètre : api/routes/**/*.php + api/app/Providers/**/*.php +
#             api/app/*/Providers/**/*.php.
# Doublon = même symbole importé deux fois (ou deux imports distincts avec le
# même alias `as Y`). Les `use` dans les closures/PHPDoc ne sont pas concernés.
#
# Usage : bash dev-hub/tools/check-duplicate-use-imports.sh [api_dir]
# Tests : bash dev-hub/tools/tests/check-duplicate-use-imports.test.sh
#
set -euo pipefail

API_DIR="${1:-api}"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "${ROOT}"

python3 - "${API_DIR}" << 'PYEOF'
import re, sys
from pathlib import Path

api_dir = Path(sys.argv[1])
files = []
for base in (api_dir / "routes", api_dir / "app" / "Providers"):
    if base.exists():
        files += sorted(base.rglob("*.php"))
for base in (api_dir / "app",):
    for prov in sorted(base.glob("*/Providers")):
        files += sorted(prov.rglob("*.php"))

USE_RE = re.compile(r"^\s*use\s+([A-Za-z0-9_\\]+)(?:\s+as\s+([A-Za-z0-9_]+))?\s*;", re.MULTILINE)

violations = []
for f in files:
    text = f.read_text(encoding="utf-8", errors="replace")
    seen = {}  # symbole/alias -> (line, import)
    for lineno, line in enumerate(text.splitlines(), start=1):
        m = USE_RE.match(line.strip())
        if not m:
            continue
        fqcn, alias = m.group(1), m.group(2)
        key = alias if alias else fqcn
        if key in seen:
            violations.append((str(f.relative_to(api_dir)), lineno, key, seen[key]))
        else:
            seen[key] = (lineno, m.group(0).strip())

if not violations:
    print(f"✅ Aucun double import `use` dans routes/ et Providers/ ({len(files)} fichiers).")
    sys.exit(0)

print("::error::Doubles imports `use` détectés (issue #5519) :", file=sys.stderr)
for f, lineno, key, first in violations:
    print(f"  - {f}:{lineno} — '{key}' déjà importé à la ligne {first[0]} ({first[1]})", file=sys.stderr)
print("", file=sys.stderr)
print("Corriger le doublon (supprimer l'import redondant ou renommer avec `as`) —", file=sys.stderr)
print("un `use` dupliqué casse le bootstrap des routes (régression #5406).", file=sys.stderr)
sys.exit(1)
PYEOF
