# Rôles internes de la plateforme — conception (issue #7553)

Date : 2026-09-16 · Statut : implémenté (backend) + consommé par `front/admin-dashboard` (#7557)

## Problème

Avant #7553, l'espace plateforme était **binaire** : la table `public.super_admins`
ne portait aucune colonne de rôle, et les groupes de routes `/api/v1/platform/*`
et `/api/v1/admin/*` partageaient le seul middleware `auth:super_admin_api`.
Conséquence : confier le support, la facturation ou l'observabilité à un
collaborateur interne imposait de lui donner **tous** les droits — provisioning
d'entreprises, impersonation, kill switches, gestion des comptes plateforme.

## Décision

Le rôle est porté par une colonne additive `super_admins.platform_role`
(varchar(32), `NOT NULL DEFAULT 'super_admin'`), contrainte par un CHECK
PostgreSQL. Les permissions sont dérivées du rôle par une **matrice en code**
(enum `PlatformRole` → `PlatformPermission`) et vérifiées à l'exécution par le
middleware `platform.permission:<perm>`.

Le défaut `super_admin` est le choix de conception central : il rend la
migration strictement rétrocompatible (tous les comptes existants conservent
leurs droits) et fail-open uniquement pour l'existant — toute nouvelle valeur
doit être explicitement distribuée depuis `/platform/team`.

## Matrice rôle → permissions

| Permission | super_admin | admin | support | finance | ops | marketing |
|---|:---:|:---:|:---:|:---:|:---:|:---:|
| `companies.view` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `companies.manage` | ✅ | ✅ | — | — | — | — |
| `companies.provision` | ✅ | ✅ | — | — | — | — |
| `billing.view` | ✅ | ✅ | — | ✅ | — | — |
| `billing.manage` | ✅ | ✅ | — | ✅ | — | — |
| `plans.view` | ✅ | ✅ | — | ✅ | — | — |
| `users.view` | ✅ | ✅ | ✅ | — | — | — |
| `users.manage` | ✅ | ✅ | ✅ | — | — | — |
| `impersonate` | ✅ | ✅ | ✅ | — | — | — |
| `killswitch.manage` | ✅ | ✅ | — | — | ✅ | — |
| `observability.view` | ✅ | ✅ | ✅ | — | ✅ | — |
| `metrics.view` | ✅ | ✅ | ✅ | ✅ | ✅ | — |
| `support.manage` | ✅ | ✅ | ✅ | — | — | — |
| `announcements.manage` | ✅ | ✅ | — | — | — | ✅ |
| `crm.view` | ✅ | ✅ | — | — | — | ✅ |
| `showcase.manage` | ✅ | ✅ | — | — | — | ✅ |
| `edge.manage` | ✅ | ✅ | — | — | ✅ | — |
| `team.manage` | ✅ | — | — | — | — | — |

Lecture : `admin` administre la plateforme mais **ne distribue pas les rôles**
(seul le propriétaire du SaaS le fait) ; chaque rôle métier reçoit la plus
petite surface qui lui permet de travailler.

## Surface d'application

`platform.permission` est posée sur les familles sensibles de `/api/v1/platform/*` :
entreprises (lecture/paramétrage/provisioning), abonnements et offres, comptes
des utilisateurs d'entreprise, impersonation, kill switches, observabilité et
métriques, support, annonces, CRM, nœuds edge, et le nouveau groupe `/team`.
Côté cockpit `/admin/*`, la garde couvre l'impersonation et le tableau de bord
(`metrics.view`).

## Garde-fous (anti auto-verrouillage)

| Règle | Erreur renvoyée |
|---|---|
| Un compte ne change pas son propre rôle | `422 CANNOT_CHANGE_OWN_PLATFORM_ROLE` |
| Un compte ne se désactive pas lui-même | `422 CANNOT_DISABLE_OWN_ACCOUNT` |
| Le dernier `super_admin` **actif** ne peut être rétrogradé ni désactivé | `422 LAST_SUPER_ADMIN_REQUIRED` |
| Désactiver un compte révoque ses tokens Sanctum | (parité #2630) |
| Un rôle hors matrice est refusé par l'API **et** par la base | `422` / CHECK PostgreSQL |
| Login d'un compte non `active` | `403 ACCOUNT_SUSPENDED` |
| Permission manquante | `403 PLATFORM_PERMISSION_REQUIRED` (+ `required_permissions`, `platform_role`) |

`platform_role` est volontairement **hors `$fillable`** (garde
`tests/Unit/SensitiveFillableGuardTest.php`) : un rôle ne s'attribue que par un
chemin explicite (console `/platform/team`, seeder, SQL d'exploitation).

## Contrat API

| Route | Permission | Effet |
|---|---|---|
| `GET /api/v1/platform/team` | `team.manage` | Liste des comptes internes + matrice (`meta.roles`) |
| `POST /api/v1/platform/team` | `team.manage` | Création (nom, e-mail, mot de passe ≥ 12, rôle) |
| `PATCH /api/v1/platform/team/{id}/role` | `team.manage` | Changement de rôle |
| `POST /api/v1/platform/team/{id}/activate` | `team.manage` | Réactivation |
| `POST /api/v1/platform/team/{id}/deactivate` | `team.manage` | Désactivation + révocation des tokens |

`GET /api/v1/platform/auth/me` expose `platform_role` et `permissions` **en plus**
de `role: "super_admin"` conservé tel quel : les clients existants
(`front/admin-dashboard`) ne cassent pas au déploiement.

## Traçabilité

- Migration : `api/database/migrations/public/2026_09_16_000001_7553_add_platform_role_to_super_admins.php`
- Enum + matrice : `api/app/Modules/Platform/Domain/Enums/PlatformRole.php`, `PlatformPermission.php`
- Middleware : `api/app/Http/Middleware/EnsurePlatformPermissionMiddleware.php` (alias `platform.permission`)
- Contrôleur : `api/app/Modules/Platform/Interfaces/Api/V1/Controllers/PlatformTeamController.php`
- Tests : `api/tests/Feature/Platform/PlatformTeamApiTest.php`
- Console : `front/admin-dashboard/src/views/team/PlatformTeamView.vue` (#7557)
