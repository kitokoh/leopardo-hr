#!/usr/bin/env python3
"""Garde de parite des sous-roles manager assignables (#9334).

Trois sources portent la meme enumeration, dans trois langages :

  api/app/Modules/HR/Infrastructure/Services/RoleInvitationService.php
      RoleInvitationService::ASSIGNABLE_MANAGER_ROLES
  front/web/src/lib/i18n/team-roles.ts
      TEAM_ASSIGNABLE_MANAGER_ROLES
  front/mobile_apps/leopardo_core/lib/features/team/manager_roles.dart
      kAssignableManagerRoles

Avant #9334, chacune avait derive de son cote : la base acceptait 10 valeurs,
l'API 6 (`in:` recopie en dur dans deux FormRequests), le web 5, le mobile 4 -
un client pouvait donc proposer un role que l'API refusait en 422.

La garde compare les TROIS listes a l'ensemble des valeurs acceptees par l'API
(`in: principal` + constante). Sortie ASCII uniquement (piege cp1252 Windows,
lecon 2026-11-08).

Usage : python3 dev-hub/tools/check-team-role-parity.py [--root DIR]
Sortie : 0 si les trois listes coincident, 1 sinon.
"""
from __future__ import annotations

import argparse
import re
import sys

PHP = 'api/app/Modules/HR/Infrastructure/Services/RoleInvitationService.php'
WEB = 'front/web/src/lib/i18n/team-roles.ts'
DART = 'front/mobile_apps/leopardo_core/lib/features/team/manager_roles.dart'
STORE = 'api/app/Modules/HR/Interfaces/Api/V1/Requests/StoreEmployeeRequest.php'
UPDATE = 'api/app/Modules/HR/Interfaces/Api/V1/Requests/UpdateEmployeeRequest.php'

EXPECTED = ['comptable', 'dept', 'marketing', 'rh', 'superviseur']


def read(root: str, path: str) -> str:
    with open(f'{root}/{path}', encoding='utf-8') as handle:
        return handle.read()


def quoted_in_block(text: str, pattern: str, label: str) -> list[str]:
    match = re.search(pattern, text, re.S)
    if not match:
        raise SystemExit(f'KO  {label}: bloc introuvable ({pattern})')
    return re.findall(r"'([^']+)'", match.group(1))


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument('--root', default='.')
    args = parser.parse_args()
    root = args.root.rstrip('/') or '.'

    php = quoted_in_block(
        read(root, PHP),
        r'ASSIGNABLE_MANAGER_ROLES\s*=\s*\[(.*?)\]',
        'PHP',
    )
    web = quoted_in_block(
        read(root, WEB),
        r'TEAM_ASSIGNABLE_MANAGER_ROLES[^=]*=\s*\[(.*?)\]',
        'web',
    )
    dart = quoted_in_block(
        read(root, DART),
        r'kAssignableManagerRoles\s*=\s*<String>\[(.*?)\]',
        'mobile',
    )

    failures: list[str] = []
    for name, values in (('PHP RoleInvitationService', php), ('web team-roles.ts', web), ('mobile manager_roles.dart', dart)):
        normalised = sorted(values)
        print(f'INFO {name}: {normalised}')
        if normalised != EXPECTED:
            failures.append(f'{name} attendu {EXPECTED}, obtenu {normalised}')

    # Les deux FormRequests doivent DERIVER de la constante, pas la recopier :
    # une liste recopiee en dur est exactement ce qui a produit la divergence.
    for path in (STORE, UPDATE):
        body = read(root, path)
        if 'RoleInvitationService::ASSIGNABLE_MANAGER_ROLES' not in body:
            failures.append(f'{path.rsplit("/", 1)[-1]} ne derive pas de RoleInvitationService::ASSIGNABLE_MANAGER_ROLES')
        if re.search(r"'in:principal,rh,", body):
            failures.append(f'{path.rsplit("/", 1)[-1]} contient encore une enumeration recopiee en dur')

    if failures:
        print('')
        for failure in failures:
            print(f'KO  {failure}')
        print('KO  parite des sous-roles manager rompue (#9334)')
        return 1

    print('OK  parite des sous-roles manager: PHP = web = mobile = ' + ','.join(EXPECTED))
    return 0


if __name__ == '__main__':
    sys.exit(main())
