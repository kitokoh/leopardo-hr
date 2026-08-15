# Mini-spécification — Issue #3594

## Objectif

Donner au service Render `leopardo-scheduler` (worker de tâches planifiées, `php artisan schedule:run`) la même configuration mail que le web service, pour que les emails synchrones émis par les tâches planifiées (rappels, alertes, notifications) partent par le SMTP réel au lieu de tomber sur `127.0.0.1:25`.

## Constat

`render.yaml` service `leopardo-scheduler` : aucun `MAIL_*` dans ses `envVars`. `config/mail.php` retombe sur `'host' => env('MAIL_HOST', '127.0.0.1')` → tout envoi depuis le scheduler échoue silencieusement.

## Décision

Copier le bloc mail du web service (`MAIL_MAILER=smtp`, `MAIL_PORT=587`, `MAIL_ENCRYPTION=tls`, `MAIL_FROM_ADDRESS=noreply@leopardo-rh.com`, `MAIL_FROM_NAME=Leopardo RH`, et `MAIL_HOST`/`MAIL_USERNAME`/`MAIL_PASSWORD` en `sync: false` pour être renseignés dans le dashboard Render).

## Critères d'acceptation

1. Le service `leopardo-scheduler` expose les 8 clés `MAIL_*` (dont `sync: false` pour les secrets).
2. `yaml.safe_load(render.yaml)` OK ; les 3 services web/worker/scheduler ont chacun 8 clés mail.
3. `git diff --check` OK.

## Plan de retour arrière

Réversion du commit ; aucune variable existante n'est supprimée (le bloc est additif).
