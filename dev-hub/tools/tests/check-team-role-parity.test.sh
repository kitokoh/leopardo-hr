#!/usr/bin/env bash
# Test de la garde de parite des sous-roles manager (#9334).
# La garde n'existe que si on l'a vue ROUGE : on construit une arborescence
# minimale, on la verifie verte, puis on fait deriver UNE des trois listes et
# on exige un echec. Aucun reseau, aucun binaire externe hors python3.
set -euo pipefail

here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
guard="$here/../check-team-role-parity.py"
tmp="$(mktemp -d)"
trap 'rm -rf "$tmp"' EXIT

mk() { mkdir -p "$tmp/$(dirname "$1")"; }

mk api/app/Modules/HR/Infrastructure/Services/RoleInvitationService.php
cat > "$tmp/api/app/Modules/HR/Infrastructure/Services/RoleInvitationService.php" <<'PHP'
<?php
class RoleInvitationService
{
    public const ASSIGNABLE_MANAGER_ROLES = ['rh', 'dept', 'comptable', 'superviseur', 'marketing'];
}
PHP

mk front/web/src/lib/i18n/team-roles.ts
cat > "$tmp/front/web/src/lib/i18n/team-roles.ts" <<'TS'
export const TEAM_ASSIGNABLE_MANAGER_ROLES: readonly TeamManagerRole[] = [
  'rh',
  'dept',
  'comptable',
  'superviseur',
  'marketing',
];
TS

mk front/mobile_apps/leopardo_core/lib/features/team/manager_roles.dart
cat > "$tmp/front/mobile_apps/leopardo_core/lib/features/team/manager_roles.dart" <<'DART'
const List<String> kAssignableManagerRoles = <String>[
  'rh',
  'dept',
  'comptable',
  'superviseur',
  'marketing',
];
DART

mk api/app/Modules/HR/Interfaces/Api/V1/Requests/StoreEmployeeRequest.php
cat > "$tmp/api/app/Modules/HR/Interfaces/Api/V1/Requests/StoreEmployeeRequest.php" <<'PHP'
<?php
'manager_role' => ['nullable', 'in:'.implode(',', [
    'principal',
    ...RoleInvitationService::ASSIGNABLE_MANAGER_ROLES,
])],
PHP
cp "$tmp/api/app/Modules/HR/Interfaces/Api/V1/Requests/StoreEmployeeRequest.php" \
   "$tmp/api/app/Modules/HR/Interfaces/Api/V1/Requests/UpdateEmployeeRequest.php"

if ! python3 "$guard" --root "$tmp" > /dev/null; then
  echo "ECHEC: la garde devait etre VERTE sur les listes alignees"
  exit 1
fi

# Le mobile perd 'marketing' (etat exact d'avant #9334) -> la garde doit etre ROUGE.
printf "const List<String> kAssignableManagerRoles = <String>[\n  'rh',\n  'dept',\n  'comptable',\n  'superviseur',\n];\n" \
  > "$tmp/front/mobile_apps/leopardo_core/lib/features/team/manager_roles.dart"
if python3 "$guard" --root "$tmp" > /dev/null 2>&1; then
  echo "ECHEC: la garde devait etre ROUGE quand le mobile derive"
  exit 1
fi

# Une constante recopiee dans un FormRequest -> ROUGE aussi.
cat > "$tmp/front/mobile_apps/leopardo_core/lib/features/team/manager_roles.dart" <<'DART'
const List<String> kAssignableManagerRoles = <String>[
  'rh',
  'dept',
  'comptable',
  'superviseur',
  'marketing',
];
DART
cat > "$tmp/api/app/Modules/HR/Interfaces/Api/V1/Requests/UpdateEmployeeRequest.php" <<'PHP'
<?php
'manager_role' => ['sometimes', 'nullable', 'in:principal,rh,dept,comptable,superviseur,marketing'],
PHP
if python3 "$guard" --root "$tmp" > /dev/null 2>&1; then
  echo "ECHEC: la garde devait etre ROUGE sur une enumeration recopiee"
  exit 1
fi

echo "OK: garde de parite des sous-roles manager (verte + 2 cas rouges)"
