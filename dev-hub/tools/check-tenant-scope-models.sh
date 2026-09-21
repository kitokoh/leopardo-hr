#!/usr/bin/env bash
# Garde #7999 — politique tenant-scope des modèles Eloquent.
#
# Règle : tout modèle de api/app/Modules dont le fichier mentionne une
# colonne `company_id` DOIT :
#   1. soit utiliser le trait App\Shared\Traits\BelongsToCompany (scope
#      global fail-closed #3727),
#   2. soit figurer dans `exceptions` (justifié) de
#      dev-hub/governance/tenant-scope-exceptions.json avec le commentaire
#      « EXCEPTION TENANT-SCOPE » dans le modèle,
#   3. soit figurer dans `legacy_pending_review` (dette pré-#7999 documentée,
#      signalée en avertissement — lot dédié à venir).
#
# Tout NOUVEAU modèle hors de ces trois cas fait échouer la garde : sans
# cela, chaque requête oubliée = fuite cross-tenant silencieuse (#7999).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
EXCEPTIONS="$ROOT/dev-hub/governance/tenant-scope-exceptions.json"
API="$ROOT/api"
VIOLATIONS=0
LEGACY=0

while IFS= read -r -d '' file; do
  rel="${file#"$API/"}"
  if ! grep -q "company_id" "$file"; then
    continue
  fi

  # FQCN du modèle
  ns="$(grep -m1 '^namespace ' "$file" | sed 's/^namespace \(.*\);/\1/')"
  cls="$(basename "$file" .php)"
  fqcn="${ns}\\${cls}"

  # Trait détecté via l'import (colonne 0) OU l'usage en corps de classe
  # (indenté, éventuellement multi-traits : `use Auditable, BelongsToCompany;`).
  if grep -Eq "^use App\\\\Shared\\\\Traits\\\\BelongsToCompany;" "$file" \
    || grep -Eq "^[[:space:]]+use[[:space:]].*\bBelongsToCompany\b" "$file"; then
    continue
  fi

  if grep -q "EXCEPTION TENANT-SCOPE" "$file" && python3 - "$fqcn" "$EXCEPTIONS" <<'PY'
import json, sys
fqcn, path = sys.argv[1], sys.argv[2]
data = json.load(open(path))
sys.exit(0 if any(e.get('model') == fqcn for e in data.get('exceptions', [])) else 1)
PY
  then
    continue
  fi

  if python3 - "$fqcn" "$EXCEPTIONS" <<'PY'
import json, sys
fqcn, path = sys.argv[1], sys.argv[2]
data = json.load(open(path))
sys.exit(0 if fqcn in data.get('legacy_pending_review', []) else 1)
PY
  then
    echo "⚠️  $rel : legacy #7730 en attente de qualification (legacy_pending_review)"
    LEGACY=$((LEGACY + 1))
    continue
  fi

  echo "❌ $rel : mentionne company_id sans BelongsToCompany ni exception canonique (#7999)"
  echo "   → ajouter le trait App\\Shared\\Traits\\BelongsToCompany OU une exception"
  echo "     documentée (commentaire EXCEPTION TENANT-SCOPE + entrée dans"
  echo "     dev-hub/governance/tenant-scope-exceptions.json)."
  VIOLATIONS=$((VIOLATIONS + 1))
done < <(find "$API/app/Modules" -path '*/Domain/Models/*.php' -print0)

echo ""
if [ "$VIOLATIONS" -gt 0 ]; then
  echo "$VIOLATIONS modèle(s) en violation de la politique tenant-scope (#7999)."
  exit 1
fi

echo "✅ Tenant-scope : aucun nouveau modèle non qualifié (#7999) — $LEGACY legacy documentés."
