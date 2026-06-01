# Correctif communication governance - 2026-06-01

## Contexte

Le smoke `LAUNCH_OBSERVABILITY_SMOKE_2026_06_01.md` a confirme que `/api/v1/launch-readiness` fonctionne, mais retourne `go_live_ready=false` car le check requis `communication_governance` detecte seulement `preferences_configured=1` pour les employes actifs du tenant demo.

## Cause

Les preferences notifications etaient creees a la demande via l'API ou le service communication. Un employe actif qui ne visitait pas encore son ecran Compte pouvait donc rester sans ligne `notification_preferences`, ce qui bloque correctement le cockpit de lancement.

## Correctif

- Ajout de `NotificationPreferenceProvisioner` comme source unique de creation/reparation des preferences.
- `NotificationPreferenceController` et `CommunicationService` utilisent ce provisioner au lieu de dupliquer les valeurs par defaut.
- Ajout de la commande ops `php artisan notifications:backfill-preferences`.
- L'entrypoint Render execute ce backfill apres migrations et seeders.
- Ajout d'un test de commande qui couvre creation manquante et reparation de scope `company_id`.

## Validation locale

- `php -l api/app/Services/Communication/NotificationPreferenceProvisioner.php` : OK.
- `php -l api/app/Console/Commands/BackfillNotificationPreferences.php` : OK.
- `php -l api/app/Http/Controllers/Api/V1/NotificationPreferenceController.php` : OK.
- `php -l api/app/Services/Communication/CommunicationService.php` : OK.
- `php -l api/tests/Feature/BackfillNotificationPreferencesCommandTest.php` : OK.

`php artisan test` n'a pas ete execute localement : le `vendor/` Windows est incomplet (`symfony/deprecation-contracts/function.php` manquant). Les tests applicatifs doivent donc etre valides par GitHub Actions.

## Effet attendu Render

Au prochain deploiement, l'entrypoint doit backfiller les preferences de tous les employes actifs. Le check `communication_governance` doit ensuite passer si `communication_failures_7d=0`.
