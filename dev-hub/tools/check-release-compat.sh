#!/usr/bin/env bash
#
# check-release-compat.sh — garde de cohérence de la matrice Release Train (MAT-016, issue #5874).
#
# Vérifie que `dev-hub/tools/release-compat-matrix.json` est cohérent avec le
# dépôt :
#   1. JSON valide + champs obligatoires (api, api_min_supported, components) ;
#   2. `api` == défaut APP_VERSION de api/config/app.php (et .env.example via
#      le garde existant check-app-version-sync.sh) ;
#   3. chaque app mobile listée existe avec une version pubspec.yaml identique
#      à `current` ;
#   4. le kiosk (front/zkteco-kiosk/package.json) correspond à `current` ;
#   5. api_min_supported ≤ api (plancher sain) ;
#   6. chaque composant expose un plancher `min_api` (aucune entrée muette).
#
# Usage : bash dev-hub/tools/check-release-compat.sh [api_dir] [matrix]
# Tests : bash dev-hub/tools/tests/check-release-compat.test.sh
#
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MATRIX="${2:-${SCRIPT_DIR}/release-compat-matrix.json}"
API_DIR="${1:-api}"
ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
cd "${ROOT}"

if [[ ! -f "${MATRIX}" ]]; then
  echo "::error::Matrice Release Train introuvable : ${MATRIX} (issue #5874)." >&2
  exit 1
fi

python3 - "${MATRIX}" "${API_DIR}" << 'PYEOF'
import json, re, sys
from pathlib import Path

matrix_path = Path(sys.argv[1])
api_dir = Path(sys.argv[2])

errors = []
def err(msg):
    errors.append(msg)

try:
    m = json.loads(matrix_path.read_text(encoding="utf-8"))
except Exception as exc:
    print(f"::error::Matrice Release Train JSON invalide : {exc} (issue #5874).", file=sys.stderr)
    sys.exit(1)

# 1. champs obligatoires
for key in ("api", "api_min_supported", "components"):
    if key not in m:
        err(f"clé obligatoire '{key}' absente de la matrice")
if "components" in m and not isinstance(m["components"], dict):
    err("'components' doit être un objet")

# 2. version API == config/app.php
app_cfg = api_dir / "config" / "app.php"
if app_cfg.exists():
    cfg_text = app_cfg.read_text(encoding="utf-8")
    mm = re.search(r"env\('APP_VERSION',\s*'([^']+)'\)", cfg_text)
    if mm and mm.group(1) != m.get("api"):
        err(f"api '{m.get('api')}' ≠ APP_VERSION config ({mm.group(1)}) — bump de version sans mise à jour de la matrice")

# 3. apps mobiles : version pubspec identique
apps = (m.get("components") or {}).get("mobile_apps") or {}
for app, spec in apps.items():
    pubspec = api_dir.parent / "front" / "mobile_apps" / app / "pubspec.yaml"
    if not pubspec.exists():
        err(f"app mobile '{app}' introuvable (front/mobile_apps/{app}/pubspec.yaml)")
        continue
    text = pubspec.read_text(encoding="utf-8")
    vm = re.search(r"^version:\s*(\S+)", text, re.MULTILINE)
    if not vm or vm.group(1) != spec.get("current"):
        err(f"app '{app}' : current '{spec.get('current')}' ≠ pubspec '{vm.group(1) if vm else '?'}'")
    if "min_api" not in spec:
        err(f"app '{app}' : plancher min_api manquant")

# 4. kiosk
kiosk = (m.get("components") or {}).get("kiosk")
if kiosk:
    pkg = api_dir.parent / "front" / "zkteco-kiosk" / "package.json"
    if pkg.exists():
        try:
            data = json.loads(pkg.read_text(encoding="utf-8"))
            if data.get("version") != kiosk.get("current"):
                err(f"kiosk : current '{kiosk.get('current')}' ≠ package.json '{data.get('version')}'")
        except Exception:
            err("kiosk package.json illisible")
    if "min_api" not in kiosk:
        err("kiosk : plancher min_api manquant")

# 5. plancher sain
api_v = tuple(int(x) for x in str(m.get("api", "0")).split(".")[:3] if x.isdigit())
floor_v = tuple(int(x) for x in str(m.get("api_min_supported", "0")).split(".")[:3] if x.isdigit())
if api_v and floor_v and api_v < floor_v:
    err(f"api_min_supported '{m.get('api_min_supported')}' > api '{m.get('api')}' — plancher incohérent")

# 6. autres composants : min_api obligatoire
for comp, spec in (m.get("components") or {}).items():
    if comp == "mobile_apps":
        continue
    if not isinstance(spec, dict) or "min_api" not in spec:
        err(f"composant '{comp}' : plancher min_api manquant")

if errors:
    print("::error::Matrice Release Train incohérente (issue #5874) :", file=sys.stderr)
    for e in errors:
        print(f"  - {e}", file=sys.stderr)
    sys.exit(1)

print(f"✅ Matrice Release Train cohérente (api {m.get('api')}, plancher {m.get('api_min_supported')}).")
PYEOF
