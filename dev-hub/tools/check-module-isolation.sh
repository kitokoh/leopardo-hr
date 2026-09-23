#!/usr/bin/env bash
# check-module-isolation.sh — garde CI issue #5584 (+ #8059 : couverture app/Http)
#
# Détecte les NOUVEAUX imports croisés entre modules PHP (App\Modules\X\
# important App\Modules\Y\, ou App\Core\X important App\Modules\Y\),
# ainsi que les imports de modules depuis app/Http (Resources, Middleware,
# Controllers Web) — canal de couplage cross-module auparavant hors radar (#8059).
#
# Les violations EXISTANTES sont gelées dans deux baselines :
#   - module-isolation-allowlist.txt : sources Modules/ et Core/ (issue #5584)
#   - http-isolation-allowlist.txt   : sources Http/ (issue #8059)
# Ces fichiers sont immuables depuis CI — tout nouvel import croisé fait échouer
# la PR. Corriger le code (Events Shared, contrats) plutôt qu'agrandir l'allowlist.
# Trajectoire de résorption Http : rapatrier chaque Resource dans son module
# propriétaire (Modules/X/Interfaces/Api/V1/Resources), convention déjà majoritaire.
#
# Usage : bash dev-hub/tools/check-module-isolation.sh [api_dir]
#
# Sortie :
#   0 si aucun nouvel import croisé
#   1 si des imports croisés non listés dans les allowlists sont trouvés
#
# Prérequis : python3, grep, comm

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ALLOWLIST="${SCRIPT_DIR}/module-isolation-allowlist.txt"
HTTP_ALLOWLIST="${SCRIPT_DIR}/http-isolation-allowlist.txt"
API_DIR="${1:-api}"
APP_DIR="${API_DIR}/app"

if [[ ! -d "${APP_DIR}/Modules" ]]; then
  echo "❌  Répertoire '${APP_DIR}/Modules' introuvable." >&2
  exit 1
fi

if [[ ! -f "${ALLOWLIST}" ]]; then
  echo "❌  Allowlist introuvable : ${ALLOWLIST}" >&2
  exit 1
fi

if [[ ! -f "${HTTP_ALLOWLIST}" ]]; then
  echo "❌  Allowlist Http introuvable : ${HTTP_ALLOWLIST}" >&2
  exit 1
fi

# ── Collecte des imports croisés actuels ──────────────────────────────────────
# Pour chaque fichier PHP sous Modules/, Core/ ou Http/, on extrait les
# `use App\...` qui traversent la frontière (Modules/X → Modules/Y,
# Core/X → Modules/Y, ou Http/* → Modules/Y).

ALL_VIOLATIONS=$(
  python3 - "${APP_DIR}" << 'PYEOF'
import re, sys
from pathlib import Path

app_dir = Path(sys.argv[1])
violations = set()

for php_file in sorted(app_dir.rglob("*.php")):
    rel = str(php_file.relative_to(app_dir))
    parts = rel.split("/")

    if parts[0] == "Modules" and len(parts) >= 2:
        source_module = f"Modules/{parts[1]}"
    elif parts[0] == "Core" and len(parts) >= 2:
        source_module = f"Core/{parts[1]}"
    elif parts[0] == "Http":
        # #8059 : app/Http (Resources, Middleware, Controllers Web) est une
        # source scannée — tout import App\Modules\X\ y est un couplage croisé.
        source_module = f"Http/{parts[1]}" if len(parts) >= 3 else "Http"
    else:
        continue

    try:
        content = php_file.read_text(encoding="utf-8")
    except Exception:
        continue

    for line in content.splitlines():
        line = line.strip()
        if not line.startswith("use App\\"):
            continue

        m = re.match(r"use App\\Modules\\(\w+)\\", line)
        if not m:
            continue
        target = f"Modules/{m.group(1)}"

        if source_module.startswith("Modules/"):
            if target != source_module:
                violations.add(f"{source_module} -> {target}")
        else:
            # Core/* et Http/* : tout import d'un module est un couplage croisé.
            violations.add(f"{source_module} -> {target}")

for v in sorted(violations):
    print(v)
PYEOF
)

# Séparation des flux : les violations Http/ sont comparées à leur propre
# allowlist (#8059), les autres à l'allowlist historique (#5584).
CURRENT_HTTP_VIOLATIONS=$(echo "${ALL_VIOLATIONS}" | grep '^Http' || true)
CURRENT_VIOLATIONS=$(echo "${ALL_VIOLATIONS}" | grep -v '^Http' || true)

# ── Comparaison contre les allowlists ────────────────────────────────────────
diff_against_allowlist() {
  # $1 = violations courantes, $2 = fichier allowlist
  local current="$1" allowlist_file="$2"
  local tmp_current tmp_allowed
  tmp_current=$(mktemp)
  tmp_allowed=$(mktemp)
  echo "${current}" | grep -v '^[[:space:]]*$' | sort > "${tmp_current}" || true
  grep -v '^#' "${allowlist_file}" | grep -v '^[[:space:]]*$' | sort > "${tmp_allowed}" || true
  comm -23 "${tmp_current}" "${tmp_allowed}" || true
  rm -f "${tmp_current}" "${tmp_allowed}"
}

NEW_VIOLATIONS=$(diff_against_allowlist "${CURRENT_VIOLATIONS}" "${ALLOWLIST}")
NEW_HTTP_VIOLATIONS=$(diff_against_allowlist "${CURRENT_HTTP_VIOLATIONS}" "${HTTP_ALLOWLIST}")

if [[ -z "${NEW_VIOLATIONS}" && -z "${NEW_HTTP_VIOLATIONS}" ]]; then
  TOTAL=$(echo "${CURRENT_VIOLATIONS}" | grep -c . || true)
  TOTAL_HTTP=$(echo "${CURRENT_HTTP_VIOLATIONS}" | grep -c . || true)
  echo "✅  Aucun nouvel import croisé (${TOTAL:-0} violations connues Modules/Core, ${TOTAL_HTTP:-0} connues Http)."
  exit 0
fi

if [[ -n "${NEW_VIOLATIONS}" ]]; then
  echo "❌  Nouveaux imports croisés détectés (issue #5584) :" >&2
  echo "${NEW_VIOLATIONS}" | sed "s/^/    /" >&2
fi
if [[ -n "${NEW_HTTP_VIOLATIONS}" ]]; then
  echo "❌  Nouveaux imports de modules depuis app/Http détectés (issue #8059) :" >&2
  echo "${NEW_HTTP_VIOLATIONS}" | sed "s/^/    /" >&2
fi
echo "" >&2
echo "Ces imports violent la règle d'isolation des modules (ARCHITECTURE.md §2)." >&2
echo "Alternatives : Events Shared, contrats (interfaces), injection de dépendance ;" >&2
echo "pour app/Http/Resources : rapatrier la Resource dans son module propriétaire." >&2
echo "NE PAS ajouter aux allowlists sans discussion architecturale documentée." >&2
exit 1
