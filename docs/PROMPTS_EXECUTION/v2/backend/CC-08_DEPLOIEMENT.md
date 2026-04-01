# CC-08 — Déploiement o2switch + Nginx + Supervisor + CI/CD
# Agent : Claude Code
# Durée : 4-6 heures
# Prérequis : CC-07 vert (php artisan test global → 0 failure)

---

## PRÉREQUIS VÉRIFIABLES

```bash
php artisan test  # 0 failure — aucune exception
git status        # 0 fichiers non committés
git log --oneline -5  # les 5 derniers commits propres
```

---

## PARTIE A — CONFIGURATION NGINX O2SWITCH

```nginx
# /etc/nginx/sites-available/leopardo-api.conf

server {
    listen 443 ssl http2;
    server_name api.leopardo-rh.com;

    root /var/www/leopardo-rh-api/public;
    index index.php;

    ssl_certificate     /etc/letsencrypt/live/api.leopardo-rh.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/api.leopardo-rh.com/privkey.pem;

    # CORS — autoriser le domaine mobile (Flutter) et le domaine web
    add_header 'Access-Control-Allow-Origin' 'https://app.leopardo-rh.com' always;
    add_header 'Access-Control-Allow-Headers' 'Authorization, Content-Type, Accept-Language' always;
    add_header 'Access-Control-Allow-Methods' 'GET, POST, PUT, DELETE, OPTIONS' always;

    # Preflight OPTIONS
    if ($request_method = 'OPTIONS') {
        return 204;
    }

    # Logs d'accès séparés par domaine
    access_log /var/log/nginx/leopardo-api-access.log;
    error_log  /var/log/nginx/leopardo-api-error.log;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;  # 5 min pour la génération PDF async
    }

    # SSE — Notifications Server-Sent Events
    location /api/v1/notifications/stream {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 3600;     # 1h pour SSE
        fastcgi_buffering off;          # OBLIGATOIRE pour SSE — sans ça, les events ne sont pas flushés
        proxy_buffering off;
        chunked_transfer_encoding on;
    }

    # Fichiers statiques — pas de PHP
    location ~* \.(css|js|png|jpg|svg|ico|woff2)$ {
        expires 1y;
        access_log off;
        add_header Cache-Control "public, immutable";
    }

    # Bloquer l'accès aux fichiers sensibles
    location ~ /\.(env|git|htaccess) {
        deny all;
    }
}

# Redirection HTTP → HTTPS
server {
    listen 80;
    server_name api.leopardo-rh.com;
    return 301 https://$host$request_uri;
}
```

---

## PARTIE B — SUPERVISOR (Queue Workers)

```ini
; /etc/supervisor/conf.d/leopardo-horizon.conf

[program:leopardo-horizon]
process_name=%(program_name)s
command=php /var/www/leopardo-rh-api/artisan horizon
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/leopardo-rh-api/storage/logs/horizon.log
stopwaitsecs=3600  ; Attendre 1h max avant de forcer l'arrêt (jobs longs = génération paie)

; Worker dédié pour les jobs de paie (priorité haute)
[program:leopardo-payroll-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/leopardo-rh-api/artisan queue:work redis --queue=payroll --tries=3 --timeout=120
numprocs=2
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/leopardo-rh-api/storage/logs/payroll-worker.log
```

---

## PARTIE C — SCRIPT DE DÉPLOIEMENT

```bash
#!/bin/bash
# deploy.sh — appelé par GitHub Actions ou manuellement

set -e  # Arrêter si une commande échoue

echo "🚀 Déploiement Leopardo RH — $(date)"

# 1. Activer le mode maintenance
php artisan down --secret="leopardo_maintenance_bypass_2026"

# 2. Mettre à jour le code
git pull origin main

# 3. Installer les dépendances (sans dev, optimisé)
composer install --no-dev --optimize-autoloader --no-interaction

# 4. Mettre en cache (configuration + routes + vues)
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 5. Exécuter les migrations (--force pour passer la confirmation en prod)
php artisan migrate --force

# 6. Redémarrer les queues proprement (attendre que les jobs en cours finissent)
php artisan horizon:terminate  # Horizon se relance automatiquement via Supervisor
sleep 5

# 7. Vider les caches applicatifs (Redis)
php artisan cache:clear

# 8. Désactiver le mode maintenance
php artisan up

# 9. Vérification post-déploiement
response=$(curl -s -o /dev/null -w "%{http_code}" https://api.leopardo-rh.com/api/v1/health)
if [ "$response" -ne 200 ]; then
    echo "❌ ERREUR : /health retourne $response — ROLLBACK"
    git revert HEAD --no-edit
    php artisan migrate:rollback
    php artisan up
    exit 1
fi

echo "✅ Déploiement réussi — /health OK"
```

---

## PARTIE D — GITHUB ACTIONS CI/CD

### tests.yml (déjà dans le repo — vérifier qu'il est complet)

```yaml
# .github/workflows/tests.yml
name: Tests Laravel

on:
  push:
    branches: [develop, main]
  pull_request:
    branches: [develop, main]
    paths:
      - 'api/**'   # ← Ne déclencher que si api/ est modifié

jobs:
  test:
    runs-on: ubuntu-latest

    services:
      postgres:
        image: postgres:16
        env:
          POSTGRES_DB: leopardo_test
          POSTGRES_USER: leopardo_user
          POSTGRES_PASSWORD: test_password
        ports: ['5432:5432']
        options: --health-cmd pg_isready --health-interval 10s

      redis:
        image: redis:7
        ports: ['6379:6379']
        options: --health-cmd "redis-cli ping" --health-interval 10s

    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP 8.3
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          extensions: pdo_pgsql, redis
          coverage: none  # Pas de coverage sur CI pour la vitesse

      - name: Install dependencies
        run: composer install --prefer-dist --no-interaction
        working-directory: api

      - name: Configure .env for tests
        run: |
          cp .env.example .env.testing
          php artisan key:generate --env=testing
        working-directory: api

      - name: Run migrations
        run: php artisan migrate --env=testing --force
        working-directory: api

      - name: Run all tests
        run: php artisan test --parallel  # Tests en parallèle pour la vitesse
        working-directory: api

      # Règle : branche backend ne touche que api/
      - name: Check branch isolation
        if: github.event_name == 'pull_request'
        run: |
          changed_files=$(git diff --name-only origin/${{ github.base_ref }}...HEAD)
          if echo "$changed_files" | grep -q "^mobile/"; then
            echo "❌ Cette PR backend touche le dossier mobile/ — INTERDIT"
            exit 1
          fi
```

### deploy.yml (déjà dans le repo — vérifier)

```yaml
# .github/workflows/deploy.yml
name: Déploiement o2switch

on:
  push:
    branches: [main]

jobs:
  deploy:
    runs-on: ubuntu-latest
    environment: production

    steps:
      - uses: actions/checkout@v4

      - name: Deploy via SSH
        uses: appleboy/ssh-action@v1
        with:
          host: ${{ secrets.O2SWITCH_HOST }}
          username: ${{ secrets.O2SWITCH_USER }}
          key: ${{ secrets.O2SWITCH_SSH_KEY }}
          script: |
            cd /var/www/leopardo-rh-api
            bash deploy.sh
```

---

## PARTIE E — CHECKLIST POST-DÉPLOIEMENT

Vérifier manuellement après chaque déploiement :

```bash
# Depuis une machine externe (pas le serveur)

# 1. Santé de l'API
curl https://api.leopardo-rh.com/api/v1/health
# Attendu : {"status":"ok","checks":{"db":true,"redis":true,"queue":true}}

# 2. Rate limiting actif
for i in $(seq 6); do
  curl -s -o /dev/null -w "%{http_code}\n" \
    -X POST https://api.leopardo-rh.com/api/v1/auth/login \
    -H "Content-Type: application/json" \
    -d '{"email":"test@test.com","password":"wrong","device_name":"test"}'
done
# Les 5 premières = 401, la 6ème doit être 429

# 3. Horizon actif
php artisan horizon:status  # Sur le serveur → "running"

# 4. Crons configurés
php artisan schedule:list  # Sur le serveur → 5 crons listés

# 5. Logs sans erreurs critiques
tail -100 storage/logs/laravel.log | grep -i "error\|exception\|critical"
# Attendu : 0 ligne
```

---

## ROLLBACK D'URGENCE

```bash
# Si le déploiement casse quelque chose en prod
git revert HEAD --no-edit
git push origin main              # Redéclenche le déploiement automatique
php artisan migrate:rollback      # Si une migration a été appliquée
php artisan cache:clear           # Vider le cache
php artisan up                    # Sortir du mode maintenance si bloqué
```

---

## CRITÈRES DE VALIDATION FINALE

```
[ ] https://api.leopardo-rh.com/api/v1/health → {"status":"ok"}
[ ] Rate limiting 429 après 5 tentatives de login
[ ] Supervisor actif : ps aux | grep horizon → processus running
[ ] Crons actifs : php artisan schedule:list → 5 commandes
[ ] Logs propres : 0 ERROR critique dans laravel.log
[ ] GitHub Actions CI : badge vert sur branche main
[ ] Isolation branches : PR backend touchant mobile/ → CI échoue
[ ] Rollback testé : git revert + redéploiement fonctionne
```

---

## COMMIT

```
chore: add Nginx config with SSE support and CORS headers
chore: add Supervisor config for Horizon and payroll queue workers
chore: add deploy.sh script with health check and automatic rollback
ci: update tests.yml with branch isolation check and parallel execution
```
