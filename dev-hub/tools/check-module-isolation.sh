#!/usr/bin/env bash
# check-module-isolation.sh — garde CI issue #5584 (+ #8059 : app/Http)
#
# Détecte les NOUVEAUX imports croisés entre modules PHP (App\Modules\X\
# important App\Modules\Y\, ou App\Core\X important App\Modules\Y\).
# Depuis #8059, scanne aussi app/Http/ (Resources, Controllers, Middleware…) :
# tout `use App\Modules\X\` depuis app/Http est un couplage croisé
# (trajectoire : rapatrier chaque Resource dans son module propriétaire,
# Modules/X/Interfaces/Api/V1/Resources).
#
# La liste des violations EXISTANTES est dans module-isolation-allowlist.txt
# (sources Modules/ et Core/) et module-isolation-http-allowlist.txt
# (sources app/Http, baseline datée 2026-09-23, issue #8059).
# Ce fichier est immuable depuis CI — tout nouvel import croisé fait échouer
# la PR. Corriger le code (Events Shared, contrats) plutôt qu'agrandir l'allowlist.
#
# Usage : bash dev-hub/tools/check-module-isolation.sh [api_dir]
#
# Sortie :
#   0 si aucun nouvel import croisé
#   1 si des imports croisés non listés dans l'allowlist sont trouvés
#
# Prérequis : php (pour valider la syntaxe si besoin), grep, awk

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ALLOWLIST="${SCRIPT_DIR}/module-isolation-allowlist.txt"
HTTP_ALLOWLIST="${SCRIPT_DIR}/module-isolation-http-allowlist.txt"
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
  echo "❌  Allowlist app/Http introuvable : ${HTTP_ALLOWLIST} (issue #8059)" >&2
  exit 1
fi

# ── Collecte des imports croisés actuels ──────────────────────────────────────
# Pour chaque fichier PHP sous Modules/ ou Core/, on extrait les `use App\...`
# qui traversent la frontière (Modules/X → Modules/Y ou Core/X → Modules/Y).

CURRENT_VIOLATIONS=$(
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
        # #8059 : app/Http (Resources, Controllers, Middleware…) est une
        # source scannée — tout import de module depuis ce canal est un
        # couplage croisé hors frontière.
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
        if m:
            target = f"Modules/{m.group(1)}"
            if source_module.startswith(("Core/", "Http")):
                violations.add(f"{source_module} -> {target}")
            elif target != source_module:
                violations.add(f"{source_module} -> {target}")

for v in sorted(violations):
    print(v)
PYEOF
)

# ── Comparaison contre l'allowlist ───────────────────────────────────────────
ALLOWED=$(cat "${ALLOWLIST}" "${HTTP_ALLOWLIST}" | grep -v '^#' | grep -v '^[[:space:]]*$' | sort)

NEW_VIOLATIONS=$(
  TMP_CURRENT=$(mktemp)
  TMP_ALLOWED=$(mktemp)
  echo "${CURRENT_VIOLATIONS}" | sort > "${TMP_CURRENT}"
  echo "${ALLOWED}" | sort > "${TMP_ALLOWED}"
  comm -23 "${TMP_CURRENT}" "${TMP_ALLOWED}" || true
  rm -f "${TMP_CURRENT}" "${TMP_ALLOWED}"
)

if [[ -z "${NEW_VIOLATIONS}" ]]; then
  TOTAL=$(echo "${CURRENT_VIOLATIONS}" | grep -c . || echo 0)
  echo "✅  Aucun nouvel import croisé (${TOTAL} violations connues dans l'allowlist)."
  exit 0
fi

echo "❌  Nouveaux imports croisés détectés (issue #5584) :" >&2
echo "${NEW_VIOLATIONS}" | sed "s/^/    /" >&2
echo "" >&2
echo "Ces imports violent la règle d'isolation des modules (ARCHITECTURE.md §2)." >&2
echo "Alternatives : Events Shared, contrats (interfaces), injection de dépendance." >&2
echo "Sources Http/* (#8059) : rapatrier la Resource dans son module propriétaire" >&2
echo "(Modules/X/Interfaces/Api/V1/Resources) plutôt que d'importer le module." >&2
echo "NE PAS ajouter à l'allowlist sans discussion architecturale documentée." >&2
exit 1
