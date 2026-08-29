#!/usr/bin/env bash
# check-crm-boundary-imports.sh
# Issue #5745 (CRM PRE) : garde CI des frontières de dépendances du module CRM client.
#
# Contexte (ADR docs/architecture/refactoring/ADR-CRM-DUAL-CONTEXTS.md,
# ADR-CRM-001/002) : le CRM client (`App\Modules\CRM`) est un bounded context
# strictement séparé du CRM commercial (Platform + Marketing), de Payroll et
# d'Accounting. Les échanges doivent passer par des contrats ou des événements,
# jamais par un import direct des modèles internes d'un autre contexte.
#
# Règles :
#   1. HARD BLOCK — `Modules/CRM/**` ne peut JAMAIS importer
#      App\Modules\{Platform,Marketing,Payroll,Accounting}\ :
#      ce sont les agrégats des contextes voisins (CRM commercial, paie,
#      comptabilité). Aucune exemption possible.
#   2. Tout autre import inter-module depuis `Modules/CRM` (ex.
#      App\Modules\Notification\) doit être listé dans
#      `crm-boundary-allowlist.txt` avec une justification (contrat partagé,
#      événement, service injecté). Sans entrée → violation.
#   3. Imports autorisés sans condition : App\Core\* (socle transversal),
#      App\Shared\* (kernel partagé), intra-module App\Modules\CRM\*.
#   4. Le module CRM n'existant pas encore sur main, la garde s'active
#      automatiquement dès que `api/app/Modules/CRM` apparaît (non bloquante
#      avant cela — pas de faux rouge sur les PRs qui ne touchent pas au CRM).
#
# La garde inverse (aucun module ne doit importer Modules/CRM) est déjà portée
# par check-module-isolation.sh / check-cross-module-imports.sh (issue #5584).
#
# Usage :
#   check-crm-boundary-imports.sh [api_dir]            # scan complet (CI)
#   check-crm-boundary-imports.sh [api_dir] --self-test # auto-test (fixtures)
# Exit : 0 = OK, 1 = violation(s), 2 = usage/erreur
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ALLOWLIST="${SCRIPT_DIR}/crm-boundary-allowlist.txt"
API_DIR="${1:-api}"
SELF_TEST=0
[[ "${2:-}" == "--self-test" ]] && SELF_TEST=1

CRM_DIR="${API_DIR}/app/Modules/CRM"
FORBIDDEN="Platform Marketing Payroll Accounting"

if [[ ! -d "$API_DIR/app/Modules" ]]; then
  echo "❌ Répertoire '${API_DIR}/app/Modules' introuvable." >&2
  exit 2
fi

if [[ ! -f "$ALLOWLIST" ]]; then
  echo "❌ Allowlist introuvable : ${ALLOWLIST}" >&2
  exit 2
fi

# ── Mode auto-test ------------------------------------------------------------
if [[ "$SELF_TEST" -eq 1 ]]; then
  TMP="$(mktemp -d)"
  trap 'rm -rf "$TMP"' EXIT
  mkdir -p "$TMP/app/Modules/CRM/Interfaces/Api/V1" "$TMP/app/Modules/CRM/Domain/Models"
  mkdir -p "$TMP/app/Modules/Platform/Domain/Models" "$TMP/app/Modules/Notification/Interfaces/Api/V1"

  # Fixture 1 : violation HARD BLOCK (Platform)
  cat > "$TMP/app/Modules/CRM/Interfaces/Api/V1/CrmAccountController.php" << 'PHP'
<?php
declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1;

use App\Modules\Platform\Domain\Models\PlatformCrmPipeline;
PHP

  # Fixture 2 : violation allowlist (Notification sans justification)
  cat > "$TMP/app/Modules/CRM/Domain/Models/CrmContact.php" << 'PHP'
<?php
declare(strict_types=1);

namespace App\Modules\CRM\Domain\Models;

use App\Modules\Notification\Domain\Models\NotificationPreference;
PHP

  set +e
  bash "$0" "$TMP" > /dev/null 2>&1
  rc_hard=$?
  set -e

  if [[ "$rc_hard" -ne 1 ]]; then
    echo "❌ self-test : la fixture avec violation aurait dû échouer (exit 1), obtenu ${rc_hard}."
    exit 1
  fi
  echo "✅ self-test 1/2 : HARD BLOCK Platform détecté (exit 1)."

  # Fixture 3 : import autorisé (Core + intra-module) → exit 0
  rm -rf "$TMP/app/Modules/CRM/Interfaces" "$TMP/app/Modules/CRM/Domain/Models/CrmContact.php"
  mkdir -p "$TMP/app/Modules/CRM/Domain/Models" "$TMP/app/Core/Tenant/Domain/Models"
  cat > "$TMP/app/Modules/CRM/Domain/Models/CrmAccount.php" << 'PHP'
<?php
declare(strict_types=1);

namespace App\Modules\CRM\Domain\Models;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\CRM\Domain\Models\CrmContact;
PHP

  set +e
  bash "$0" "$TMP" > /dev/null 2>&1
  rc_ok=$?
  set -e

  if [[ "$rc_ok" -ne 0 ]]; then
    echo "❌ self-test : la fixture propre aurait dû passer (exit 0), obtenu ${rc_ok}."
    exit 1
  fi
  echo "✅ self-test 2/2 : imports Core/intra-module autorisés (exit 0)."
  exit 0
fi

# ── Mode scan -----------------------------------------------------------------
if [[ ! -d "$CRM_DIR" ]]; then
  echo "ℹ️  Module CRM client pas encore présent (${CRM_DIR}) — garde en veille (issue #5745)."
  exit 0
fi

# Parse allowlist : lignes "CRM -> <Target> | justification"
ALLOWED_TMP="$(mktemp)"
trap 'rm -f "$ALLOWED_TMP"' EXIT
while IFS= read -r line; do
  [[ -z "$line" || "$line" =~ ^[[:space:]]*# ]] && continue
  allow_re='^CRM[[:space:]]*->[[:space:]]*([A-Za-z]+)'
  if [[ "$line" =~ $allow_re ]]; then
    echo "${BASH_REMATCH[1]}" >> "$ALLOWED_TMP"
  fi
done < "$ALLOWLIST"

ALLOWED_TMP="$ALLOWED_TMP" python3 - "$CRM_DIR" << 'PYEOF'
import os, re, sys
from pathlib import Path

crm_dir = Path(sys.argv[1])
forbidden = {"Platform", "Marketing", "Payroll", "Accounting"}

# Allowlist targets passed by bash
with open(os.environ["ALLOWED_TMP"]) as fh:
    allowed = {line.strip() for line in fh if line.strip()}

violations = []
for php_file in sorted(crm_dir.rglob("*.php")):
    rel = str(php_file.relative_to(crm_dir))
    try:
        content = php_file.read_text(encoding="utf-8")
    except (UnicodeDecodeError, OSError):
        continue
    for m in re.finditer(r"^use\s+App\\Modules\\([A-Za-z]+)\\", content, re.MULTILINE):
        target = m.group(1)
        if target == "CRM":
            continue
        if target in forbidden:
            violations.append(f"{rel} -> App\\Modules\\{target}\\  [HARD BLOCK : contexte voisin interdit]")
        elif target not in allowed:
            violations.append(f"{rel} -> App\\Modules\\{target}\\  [import inter-module non exempté — ajouter à crm-boundary-allowlist.txt avec justification]")

if not violations:
    print(f"✅ CRM boundary imports OK ({sum(1 for _ in crm_dir.rglob('*.php'))} fichiers scannés).")
    sys.exit(0)

print("")
print("══════════════════════════════════════════════════════════════")
print("  CRM BOUNDARY GUARD — imports interdits (issue #5745)")
print("══════════════════════════════════════════════════════════════")
print("")
print("  Le module CRM client (App\\Modules\\CRM) ne peut pas importer :")
print("   - les agrégats du CRM commercial (Platform, Marketing), de Payroll")
print("     ou d'Accounting (HARD BLOCK, ADR-CRM-001, issue #5745) ;")
print("   - un autre module sans exemption justifiée dans")
print("     dev-hub/tools/crm-boundary-allowlist.txt.")
print("")
print("  Violation(s) :")
for v in violations:
    print(f"    ❌  {v}")
print("")
print("  Fix : passer par un contrat partagé (App\\Shared), un événement,")
print("  un service injecté, ou documenter l'exemption dans l'allowlist.")
print("")
sys.exit(1)
PYEOF
