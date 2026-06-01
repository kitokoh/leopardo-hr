# Backfill readiness demo - 2026-06-01

## Contexte

Apres le correctif `communication_governance`, le smoke Render indique :

- `score=57`
- `go_live_ready=false`
- `required_blockers=0`

Les checks restants sont optionnels mais necessaires pour franchir le seuil `70` :

- `payroll_base` : 10 employes prets sur 11 actifs.
- `attendance_entry` : pas de geofence ni kiosque actif.
- `client_experience_tracking` : aucun evenement client recent.

## Correctif

`DemoCompanyOnceSeeder` backfill maintenant les demos existantes, meme si le lock `demo_company_seed_v2` est deja pose :

- complete un salaire actif minimal pour les employes demo actifs sans base paie ;
- ajoute une geofence demo dans `public.companies.metadata.attendance_geofence` ;
- ajoute un kiosque actif si aucun kiosque actif n'existe ;
- ajoute un evenement `launch_readiness_backfilled` si aucun evenement client recent n'existe.

## Garde de regression

`DemoUserControllerTest::test_demo_once_seeder_backfills_launch_readiness_signals_for_existing_demos` couvre le cas d'une demo deja presente avec lock/skip implicite.

## Validation locale

- `php -l api/database/seeders/DemoCompanyOnceSeeder.php` : attendu.
- `php -l api/tests/Feature/DemoUserControllerTest.php` : attendu.
- PHPUnit complet : GitHub Actions, car le `vendor/` Windows local est incomplet.
