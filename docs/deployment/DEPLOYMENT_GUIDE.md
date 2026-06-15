# DEPLOYMENT GUIDE - LEOPARDO RH

Derniere mise a jour : 2026-06-01

Ce guide documente le deploiement operationnel minimal de l'API Laravel et des workers. Il complete les runbooks existants sans remplacer les configurations cloud.

## 1. Surfaces de deploiement

| Surface | Role | Plateforme cible | Notes |
|---|---|---|---|
| API Laravel | API interne, mobile, admin, kiosk, integrateurs | Render Web Service | Service HTTP public, migrations au demarrage selon entrypoint existant |
| Worker queues | Jobs asynchrones paie, PDF, notifications, exports | Render Background Worker ou Supervisor | Doit partager le meme code, `.env`, Redis et base PostgreSQL |
| Scheduler Laravel | Commandes planifiees (`schedule:run`) | Render Cron Job ou worker dedie | Requis pour `monitor:slow-queries` et futurs jobs periodiques |
| Admin dashboard | Cockpit plateforme interne | Cloudflare Pages | Projet distinct de l'API |
| Vitrine web | Marketing / portail public | Vercel | Projet distinct de l'API et de l'admin |

## 2. Variables critiques

Les services API, worker et scheduler doivent utiliser les memes variables critiques :

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://gestionemployerbackend.onrender.com
DB_CONNECTION=pgsql
DATABASE_URL=...
CACHE_STORE=redis
QUEUE_CONNECTION=redis
REDIS_CLIENT=predis
REDIS_SCHEME=tls
REDIS_URL=...
PAYROLL_QUEUE_PDF_WARMUP=true
HR_REPORT_HEADCOUNT_CACHE_TTL=60
LOG_CHANNEL=stack
```

Les secrets doivent rester dans le dashboard du fournisseur cloud. Ne pas les committer.

## 2.1 Secrets CI/CD et mobile

Les workflows GitHub Actions et Firebase Distribution attendent aussi :

```env
RENDER_DEPLOY_HOOK_URL=...
RENDER_ROLLBACK_HOOK_URL=...
FIREBASE_TOKEN=...
FIREBASE_EMPLOYEE_ANDROID_APP_ID=...
FIREBASE_MANAGER_ANDROID_APP_ID=...
FIREBASE_PLATFORM_ADMIN_ANDROID_APP_ID=...
FIREBASE_SERVICE_ACCOUNT_JSON=...
FIREBASE_READBACK_REQUIRED=false
FIREBASE_PROJECT_ID=leopardo-rh
```

Regles :

- `FIREBASE_SERVICE_ACCOUNT_JSON` doit etre rote si la cle a ete exposee dans un chat, ticket, log ou document public.
- `FIREBASE_READBACK_REQUIRED=true` ne doit etre active qu'apres avoir confirme que le service account peut lister les releases App Distribution.
- les workflows `deploy-main.yml` et `mobile-distribute.yml` comparent les App IDs Firebase avec les fichiers `google-services.json` avant upload.
- les APK doivent garder un nom prefixe par app (`employee-*`, `manager-*`, `platform-admin-*`).

## 3. Render Web Service - API

Commande de demarrage recommandee :

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan migrate --force
php artisan db:seed --class=DatabaseSeeder --force
frankenphp run --config /etc/caddy/Caddyfile
```

Si le projet utilise deja un entrypoint Docker, garder l'entrypoint comme source de verite et verifier qu'il :

- cree les tables de repository migrations public/shared_tenants ;
- execute les migrations idempotentes avec `--force` ;
- ne relance aucune requete SQL dans un `catch` PostgreSQL apres `42P07` ;
- demarre FrankenPHP/Caddy uniquement apres migrations et seeders controles.

## 4. Render Background Worker - queues

Creer un service Render separe de type Background Worker, branche `main`, meme image/build que l'API, avec cette commande :

```bash
php artisan config:cache
php artisan queue:work redis --queue=documents,pdf,payroll,notifications,webhooks,default --sleep=3 --tries=3 --timeout=300 --max-time=3600
```

Regles :

- un worker ne doit pas servir de trafic HTTP ;
- `QUEUE_CONNECTION` doit etre `redis` en production ;
- `REDIS_CLIENT` doit rester `predis` avec Upstash, surtout quand TLS est actif ;
- la queue `documents` est obligatoire pour les recus et bordereaux de paiement asynchrones ;
- `--max-time=3600` force un recyclage regulier pour eviter les fuites memoire ;
- augmenter les workers horizontalement avant d'augmenter fortement `--timeout` ;
- garder `PAYROLL_QUEUE_PDF_WARMUP=true` si les PDF bulletins doivent etre pre-generes apres validation paie.

Commande de verification worker :

```bash
php artisan queue:health-check
```

Elle doit retourner Redis `ok`, les profondeurs `documents`, `pdf`, `payroll`, `notifications`, `webhooks`, `default`, et un compteur `failed_jobs`.

## 5. Scheduler

Option simple Render Cron Job, toutes les minutes :

```bash
php artisan schedule:run --no-interaction
```

Commandes actuellement importantes :

- `monitor:slow-queries --threshold=500` toutes les 15 minutes via `bootstrap/app.php` ;
- futurs exports/relances doivent passer par le scheduler, pas par des sleeps dans les workers.

## 6. Supervisor hors Render

Sur VPS, installer Supervisor et creer deux programmes separes :

```ini
[program:leopardo-queue]
command=php /var/www/leopardo-rh-api/artisan queue:work redis --queue=documents,pdf,payroll,notifications,webhooks,default --sleep=3 --tries=3 --timeout=300 --max-time=3600
directory=/var/www/leopardo-rh-api
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
stdout_logfile=/var/www/leopardo-rh-api/storage/logs/queue-worker.log
stderr_logfile=/var/www/leopardo-rh-api/storage/logs/queue-worker.err.log

[program:leopardo-scheduler]
command=php /var/www/leopardo-rh-api/artisan schedule:work
directory=/var/www/leopardo-rh-api
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
stdout_logfile=/var/www/leopardo-rh-api/storage/logs/scheduler.log
stderr_logfile=/var/www/leopardo-rh-api/storage/logs/scheduler.err.log
```

Puis :

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status
```

## 7. Checks post-deploy

Apres chaque deploiement production :

```bash
php artisan about
php artisan migrate:status
php artisan queue:health-check
php artisan queue:failed
php artisan route:list --path=api/v1/health
```

Verifier aussi :

- `GET /api/v1/health` retourne `200` ;
- les jobs de validation paie creent les chemins PDF quand `PAYROLL_QUEUE_PDF_WARMUP=true` ;
- aucun `queue:failed` critique ;
- les logs Render ne montrent pas `SQLSTATE[42P07]` ou `SQLSTATE[25P02]` pendant les migrations ;
- le worker redemarre automatiquement apres crash ou deploy.

## 8. Rollback

En cas de deploy API defectueux :

1. rollback Render vers le dernier deploy vert ;
2. relancer le worker sur le meme commit que l'API ;
3. verifier `queue:failed` avant de rejouer des jobs ;
4. ne jamais rollbacker la base par suppression manuelle sans suivre `docs/GESTION_PROJET/RUNBOOK_ROLLBACK.md`.

## 9. Definition de pret-production

Le setup worker est considere pret quand :

- API web, worker queue et scheduler sont trois processus distincts ;
- Redis est actif pour queues/cache ;
- migrations idempotentes passent sur Render ;
- `queue:failed` est surveille ;
- les jobs paie/PDF/notifications sont executables sans action manuelle ;
- un rollback documente existe et a ete teste au moins une fois en staging.
