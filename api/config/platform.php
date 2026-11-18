<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Plateforme (super-administration)
|--------------------------------------------------------------------------
|
| IAM-L6 (#9096) — la MFA est IMPOSÉE aux comptes de la plateforme.
|
| Avant ce réglage, la MFA d'un super-admin était purement volontaire : le
| login ne demandait un code que SI `two_fa_secret` était déjà posé, et rien
| n'obligeait à le poser. Un compte `super_admin` sans second facteur ouvrait
| donc une session omnisciente avec un seul mot de passe — la protection
| existait dans le code, mais elle était facultative.
|
| `mfa_required = true` ferme ce trou : un compte de plateforme sans second
| facteur n'obtient plus de session utilisable, seulement de quoi S'ENRÔLER.
|
| Récupération (documentée) : le compte enrôle la MFA via
| `POST /platform/auth/2fa/setup` puis `enable`, appelables avec le jeton
| d'enrôlement restreint. Un compte ayant perdu son appareil passe par la
| désactivation (`disable2fa`, protégée par mot de passe) puis ré-enrôle.
| Aucune opération destructive, aucune migration.
|
*/

return [
    /*
     | Imposer un second facteur à tout compte de plateforme (`super_admins`).
     |
     | `true` en production : un compte sans `two_fa_secret` n'obtient qu'un
     | jeton restreint à l'enrôlement MFA. Le harness de test positionne
     | `PLATFORM_MFA_REQUIRED=false` pour ne pas réécrire tous les scénarios
     | existants ; les tests dédiés la réactivent et prouvent le refus.
     */
    'mfa_required' => env('PLATFORM_MFA_REQUIRED', true),

    /*
     | Routes qu'un jeton d'enrôlement restreint peut ouvrir. Volontairement
     | limitées à l'enrôlement : ni données, ni écriture, ni impersonation.
     */
    'mfa_enrollment_paths' => [
        'api/v1/platform/auth/2fa/setup',
        'api/v1/platform/auth/2fa/enable',
        'api/v1/platform/auth/me',
        'api/v1/platform/auth/logout',
    ],
];
