# PLAN 29 - Mobile Platform Admin

## Objectif

Creer une troisieme application mobile dediee a l'administration de la plateforme Leopardo RH. Cette app est reservee aux super-admins et ne doit pas melanger les workflows tenant employee/manager.

## Architecture cible

| App | Persona | Package | API |
|---|---|---|---|
| `leopardo_employee` | Employes | `com.leopardo.employee` | `/auth`, `/attendance`, `/absences`, `/salary-advances` |
| `leopardo_manager` | Manager/RH | `com.leopardo.manager` | API tenant manager/RH |
| `leopardo_platform_admin` | Super-admin plateforme | `com.leopardo.platformadmin` | `/platform/*` |

## Lot 29.1 - Socle livre

- Nouveau dossier `front/mobile_apps/leopardo_platform_admin`.
- Identites Android/iOS distinctes : `com.leopardo.platformadmin`.
- Dependence unique au socle partage `leopardo_core`.
- Login super-admin, hydration session, logout.
- Cockpit metriques plateforme.
- Liste entreprises clientes.
- Creation d'une entreprise cliente.
- Validation/refus des demandes clients.
- Garde CI `validate-mobile-plan29.ps1`.
- Build debug ajoute au workflow `Mobile Apps CI - Flutter`.

## Regles d'architecture

- Aucune route employee/manager dans l'app platform admin.
- Aucun endpoint tenant RH dans l'app platform admin.
- Toute brique partagee reste dans `leopardo_core`.
- Toute action sensible doit passer par les API `/platform/*` deja protegees par `auth:super_admin_api` et `throttle:platform-sensitive`.
- La distribution Firebase de cette app attend la creation de l'app Firebase `com.leopardo.platformadmin` et les secrets dedies.

## Lots suivants

1. Activer Firebase App Distribution pour `leopardo_platform_admin` avec `FIREBASE_PLATFORM_ADMIN_ANDROID_APP_ID`.
2. Rendre le readback strict en renseignant `FIREBASE_SERVICE_ACCOUNT_JSON` dans GitHub Secrets.
3. Ajouter une gestion avancee des plans, features, health et abonnements client.
4. Ajouter audit log mobile super-admin pour chaque action sensible.
5. Ajouter E2E mobile/API super-admin avec compte demo.
