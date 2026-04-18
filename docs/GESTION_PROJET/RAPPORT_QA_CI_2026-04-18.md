# RAPPORT QA CI — 2026-04-18

## Objectif

Renforcer la qualite des validations backend/mobile en CI, fiabiliser le reset Super Admin pour les tests en ligne, et documenter un protocole de verification pro.

## Changements appliques

### 1) Backend CI plus complet

- Workflow: `.github/workflows/tests.yml`
- Migrations executees par schema:
  - `php artisan migrate --path=database/migrations/public --force --isolated`
  - `php artisan migrate --path=database/migrations/tenant --force --isolated`
- Tests backend separes:
  - `php artisan test --testsuite=Unit --log-junit=storage/test-results/unit.xml`
  - `php artisan test --testsuite=Feature --log-junit=storage/test-results/feature.xml`
- Artefacts uploades:
  - `api/storage/test-results/*.xml`
  - `api/storage/logs/*.log`
- Scenarios backend de reference documentes:
  - `docs/GESTION_PROJET/SCENARIOS_TEST_API_GITHUB_ACTIONS.md`
- Couverture backend renforcee avec nouveaux tests:
  - garde-fous auth (`AuthLoginGuardrailsTest`)
  - contrats JSON critiques mobile (`MobilePayloadContractTest`)
  - isolation estimation inter-tenant (`Feature/Estimation/TenantIsolationTest`)

### 2) Mobile CI

- Mobile ne s'execue que si des fichiers `mobile/**` ont change.
- Si mobile execute:
  - `flutter test --coverage`
  - artefact couverture: `mobile/coverage/lcov.info`
- Scenarios mobile de reference documentes:
  - `docs/GESTION_PROJET/SCENARIOS_TEST_MOBILE_FLUTTER.md`

### 3) Super Admin (test online)

- Seeder mis a jour: `api/database/seeders/SuperAdminSeeder.php`
  - Reset force possible si:
    - `FORCE_SUPER_ADMIN_PASSWORD_RESET=true`
    - `SUPER_ADMIN_PASSWORD=<mot_de_passe>`
- Commande artisan ajoutee: `api/routes/console.php`
  - `php artisan super-admin:reset-password <email> <password>`

## Pourquoi c'est plus pro

- Visibilite claire des pannes via rapports JUnit.
- Couverture separee Unit/Feature = diagnostic rapide.
- Migrations CI alignees sur le modele public/tenant reel.
- Flux de reset Super Admin reproductible sans toucher la base manuellement.
- Les scenarios attendus sont maintenant formalises separement pour l'API et le mobile.
- La CI backend couvre maintenant explicitement les contrats critiques consommes par le mobile.

## Procedure de validation recommandee

1. Ouvrir la PR et verifier les checks:
   - Backend
   - Mobile (si changements mobile)
   - Governance
2. En cas d'echec backend, ouvrir l'artefact `backend-test-reports`.
3. En cas de test online Super Admin:
   - definir `SUPER_ADMIN_PASSWORD`
   - definir `FORCE_SUPER_ADMIN_PASSWORD_RESET=true`
   - redeployer une fois
   - remettre `FORCE_SUPER_ADMIN_PASSWORD_RESET=false`

## Limites connues

- Le mot de passe actuel d'un Super Admin existant ne peut pas etre lu (hash irreversible) ; il doit etre reinitialise.
- Les tests CI ne remplacent pas un run d'acceptance manuel complet des parcours metier.
- Certains domaines backend restent a automatiser completement en CI: register public, conges, payroll, blocked user dedie.
