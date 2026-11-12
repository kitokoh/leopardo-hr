#!/usr/bin/env python3
"""Garde de parite des sous-roles manager assignables (#9334).

La source de verite est UNIQUE, et c'est le PHP :

    api/app/Modules/HR/Infrastructure/Services/RoleInvitationService.php
        RoleInvitationService::ASSIGNABLE_MANAGER_ROLES

Les deux clients doivent proposer EXACTEMENT cet ensemble :

    front/web/src/lib/i18n/team-roles.ts
        TEAM_ASSIGNABLE_MANAGER_ROLES
    front/mobile_apps/leopardo_core/lib/features/team/manager_roles.dart
        kAssignableManagerRoles

Pourquoi la reference est la constante PHP et non une liste figee dans ce
script : une quatrieme copie en dur - meme dans une garde - derive comme les
trois autres. Ajouter un role se fait donc a UN seul endroit, et la garde
signale les clients en retard au lieu de reclamer de mettre a jour une liste
de reference. C'est exactement le defaut que #9334 a corrige cote API.

Second verrou : les FormRequests ne doivent pas recopier l'enumeration
`in:principal,rh,...` - c'est la cause racine du defaut (base 10, API 6,
web 5, mobile 4).

Sortie ASCII uniquement (piege cp1252 Windows, lecon 2026-11-08).

Usage : python3 dev-hub/tools/check-team-role-parity.py [--root DIR]
Sortie : 0 si web et mobile coincident avec PHP, 1 sinon.
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


def read(root: str, path: str) -> str:
    with open(f'{root}/{path}', encoding='utf-8') as handle:
        return handle.read()


def quoted_in_block(text: str, pattern: str, label: str) -> list[str]:
    match = re.search(pattern, text, re.S)
    if not match:
        raise SystemExit(f'KO  {label}: bloc introuvable')
    return sorted(re.findall(r"'([^']+)'", match.group(1)))


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument('--root', default='.')
    args = parser.parse_args()
    root = args.root.rstrip('/') or '.'

    reference = quoted_in_block(
        read(root, PHP),
        r'ASSIGNABLE_MANAGER_ROLES\s*=\s*\[(.*?)\]',
        'PHP',
    )
    if not reference:
        print('KO  la constante PHP RoleInvitationService::ASSIGNABLE_MANAGER_ROLES est vide ou introuvable')
        return 1

    print(f'INFO reference PHP: {reference}')

    failures: list[str] = []
    clients = (
        ('web team-roles.ts', WEB, r'TEAM_ASSIGNABLE_MANAGER_ROLES[^=]*=\s*\[(.*?)\]'),
        ('mobile manager_roles.dart', DART, r'kAssignableManagerRoles\s*=\s*<String>\[(.*?)\]'),
    )
    for name, path, pattern in clients:
        values = quoted_in_block(read(root, path), pattern, name)
        print(f'INFO {name}: {values}')
        if values != reference:
            missing = [role for role in reference if role not in values]
            extra = [role for role in values if role not in reference]
            failures.append(f'{name} diverge de la reference PHP (manquants: {missing or "aucun"}, en trop: {extra or "aucun"})')

    for path in (STORE, UPDATE):
        body = read(root, path)
        name = path.rsplit('/', 1)[-1]
        if 'RoleInvitationService::' not in body:
            failures.append(f'{name} ne derive pas de RoleInvitationService')
        if re.search(r"'in:principal,rh,", body):
            failures.append(f'{name} contient encore une enumeration recopiee en dur')

    if failures:
        print('')
        for failure in failures:
            print(f'KO  {failure}')
        print('KO  parite des sous-roles manager rompue (#9334)')
        return 1

    print('OK  parite des sous-roles manager: web = mobile = reference PHP = ' + ','.join(reference))
    return 0


if __name__ == '__main__':
    sys.exit(main())
