# Feature Specification: Super-admin démo — alignement email par défaut (Closes #3775)

**Feature Branch**: `fix/3775-demo-super-admin-email`
**Created**: 2026-08-16 | **Status**: In progress
**Issue**: #3775 (P1, ops — volet code) · racine de #2646

## Contexte

Le parcours super-admin démo (`admin@leopardo-rh.com` / `password123`) est KO
en prod (#2646). **Cause racine trouvée dans le code** (pas seulement un
problème de déploiement) :

- `SuperAdminSeeder` crée le compte avec `env('SUPER_ADMIN_EMAIL', 'admin@leopardo-rh.com')`.
- `DemoCompanyOnceSeeder::syncDemoSuperAdmin()` synchronise le mot de passe démo
  sur `config('demo.super_admin_email')`.
- `api/config/demo.php` fixait ce défaut à **`admin@example.com`** → sans
  `SUPER_ADMIN_EMAIL` dans l'environnement (cas nominal), le sync ciblait un
  compte inexistant → no-op silencieux → `INVALID_CREDENTIALS` pour le vrai
  compte démo, dont le mot de passe restait aléatoire.

Second défaut : quand le compte cible n'existe pas, le seeder ne disait rien —
un opérateur ne pouvait pas diagnostiquer pourquoi le parcours démo échouait.

## User Stories & Testing

### User Story 1 — Le compte démo fonctionne sans configuration (P1)

En tant qu'opérateur déployant avec `DEMO_MODE_ENABLED=true` et aucune variable
`SUPER_ADMIN_EMAIL`, je veux que `admin@leopardo-rh.com` / `password123` se
connecte après les seeders.

**Acceptance Scenarios**:
1. Given `DEMO_MODE_ENABLED=true` sans `SUPER_ADMIN_EMAIL`, When les seeders
   tournent, Then `admin@leopardo-rh.com` a le mot de passe démo `password123`.
2. Given `SUPER_ADMIN_EMAIL` positionné, When les seeders tournent, Then le sync
   cible l'email surchargé (pas le défaut).
3. Given `DEMO_MODE_ENABLED` absent/false, When les seeders tournent, Then le
   mot de passe du super-admin n'est PAS touché (aucun backdoor silencieux).
4. Given le compte cible inexistant, When le seeder tourne, Then un warning
   visible explique pourquoi le parcours démo ne peut pas être synchronisé.

### Edge Cases

- `DISABLE_DEMO_SEEDING=true` : `syncDemoSuperAdmin()` s'exécute quand même
  (ordre actuel du `run()`) — comportement conservé.
- `two_fa_secret` présent : le sync le réinitialise (comportement existant conservé).
- Email surchargé + compte jamais créé : warning, pas de création automatique
  (créer un compte privilégié connu automatiquement = risque de sécurité).

## Requirements

### Functional Requirements

- **FR-001**: `config/demo.php` DOIT utiliser le même défaut que
  `SuperAdminSeeder` : `env('SUPER_ADMIN_EMAIL', 'admin@leopardo-rh.com')`.
- **FR-002**: quand le compte cible du sync démo n'existe pas, le seeder DOIT
  émettre un warning explicite (email + cause) au lieu d'un no-op silencieux.
- **FR-003**: le sync démo NE DOIT PAS créer de compte privilégié automatiquement.
- **FR-004**: test de régression couvrant : défaut aligné, surcharge config,
  mode démo off (non-touch).

## Success Criteria

- **SC-001**: `DemoSuperAdminSyncTest` vert (3 scénarios).
- **SC-002**: PHPStan strict vert, Pint propre.
- **SC-003**: aucun changement de comportement hors mode démo.

## Assumptions

- Le déploiement prod (#3767) et les variables Render restent du ressort des
  tâches ops ; ce ticket couvre le volet seed/code de #3775.
- `admin@leopardo-rh.com` reste le défaut documenté (`docs/DEMO_ACCOUNTS.md`).
