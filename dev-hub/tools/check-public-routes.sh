#!/usr/bin/env bash
#
# check-public-routes.sh — garde CI « routes publiques attendues » (issue #5519).
#
# Vérifie que toutes les routes publiques canoniques (portail, webhooks, SSO,
# kiosques, sondes…) sont bien présentes dans `php artisan route:list` :
# une route publique perdue silencieusement par un merge (controller existant
# mais non routé, #5495/#5377) fait échouer la CI avec un message clair.
#
# Usage : bash dev-hub/tools/check-public-routes.sh [api_dir] [--route-list <fichier.json>]
#   --route-list : injecte une liste de routes pré-générée (tests) au lieu de
#                  lancer `php artisan route:list --json`.
#
# Tests : bash dev-hub/tools/tests/check-public-routes.test.sh
#
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CANONICAL="${SCRIPT_DIR}/public-routes-canonical.txt"
API_DIR="api"
ROUTE_LIST_FILE=""

while [[ $# -gt 0 ]]; do
  case "$1" in
    --route-list)
      ROUTE_LIST_FILE="$2"
      shift 2
      ;;
    *)
      API_DIR="$1"
      shift
      ;;
  esac
done

if [[ ! -f "${CANONICAL}" ]]; then
  echo "::error::Liste canonique introuvable : ${CANONICAL} (issue #5519)." >&2
  exit 1
fi

if [[ -z "${ROUTE_LIST_FILE}" ]]; then
  if [[ ! -f "${API_DIR}/artisan" ]]; then
    echo "::error::Artisan introuvable dans '${API_DIR}' — passer --route-list ou un api_dir valide (issue #5519)." >&2
    exit 1
  fi
  ROUTE_LIST_FILE="$(mktemp)"
  trap 'rm -f "${ROUTE_LIST_FILE}"' EXIT
  (cd "${API_DIR}" && php artisan route:list --json) > "${ROUTE_LIST_FILE}" 2>/dev/null || {
    echo "::error::php artisan route:list a échoué — bootstrap PHP cassé (issue #5519)." >&2
    exit 1
  }
fi

python3 - "${ROUTE_LIST_FILE}" "${CANONICAL}" << 'PYEOF'
import json, sys
from pathlib import Path

route_list_file = Path(sys.argv[1])
canonical_file = Path(sys.argv[2])

try:
    routes = json.loads(route_list_file.read_text(encoding="utf-8"))
except Exception as exc:
    print(f"::error::route:list illisible ({route_list_file.name}) : {exc} (issue #5519).", file=sys.stderr)
    sys.exit(1)

# Normalisation : GET|HEAD -> GET, uri sans slash initial.
actual = set()
for r in routes:
    method = (r.get("method") or "GET").split("|")[0].upper()
    uri = (r.get("uri") or "").lstrip("/")
    actual.add(f"{method} {uri}")

missing = []
for ln in canonical_file.read_text(encoding="utf-8").splitlines():
    ln = ln.strip()
    if not ln or ln.startswith("#"):
        continue
    parts = ln.split(None, 1)
    if len(parts) != 2:
        continue
    method = parts[0].split("|")[0].upper()
    uri = parts[1].lstrip("/")
    key = f"{method} {uri}"
    if key not in actual:
        missing.append(key)

if not missing:
    print(f"✅ Routes publiques canoniques toutes routées ({len(actual)} routes au total).")
    sys.exit(0)

print("::error::Routes publiques canoniques MANQUANTES dans route:list (issue #5519) :", file=sys.stderr)
for m in missing:
    print(f"  - {m}", file=sys.stderr)
print("", file=sys.stderr)
print("Un merge a probablement retiré le routage d'un controller existant (régression #5495/#5377).", file=sys.stderr)
print("Restaurer la route ou retirer l'entrée de la liste canonique uniquement si la suppression", file=sys.stderr)
print("est volontaire et documentée.", file=sys.stderr)
sys.exit(1)
PYEOF
