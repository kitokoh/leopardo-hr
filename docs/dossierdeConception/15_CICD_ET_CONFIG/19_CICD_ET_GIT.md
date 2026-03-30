# WORKFLOWS CI/CD ET BRANCHES GIT — LEOPARDO RH
# Version 1.0 | Mars 2026

---

## 1. STRATÉGIE DES BRANCHES GIT (GitFlow simplifié)

### Branches permanentes :
- `main` : Production. Toujours stable et testée.
- `develop` : Intégration. Base des nouvelles fonctionnalités.

### Branches temporaires :
- `feature/*` : Nouvelle fonctionnalité. Part de `develop`.
- `bugfix/*` : Correction de bug. Part de `develop`.
- `hotfix/*` : Correction urgente. Part de `main` (pour retour rapide en prod).

---

## 2. CI/CD (GitHub Actions)

### Workflow A : Tests Automatiques (`tests.yml`)
Déclenché à chaque **Pull Request** vers `develop` ou `main`.
- **Backend** : Installation PHP, Composer, PostgreSQL, Redis, puis `php artisan test`.
- **Mobile** : Installation Flutter, puis `flutter test`.
*Action :* Empêche le merge si les tests échouent.

### Workflow B : Déploiement o2switch (`deploy.yml`)
Déclenché à chaque **Push** sur `main`.
- Connexion SSH au VPS o2switch.
- `git pull origin main`.
- `composer install --no-dev`.
- `php artisan migrate --force`.
- `php artisan config:cache`, `route:cache`, `view:cache`.
- `sudo supervisorctl restart all` (Queue workers).

---

## 3. CONVENTIONS DE COMMITS

Nous utilisons les **Conventional Commits** :
- `feat:` : Nouvelle fonctionnalité.
- `fix:` : Correction de bug.
- `docs:` : Mise à jour de la documentation.
- `style:` : Mise en forme, virgules manquantes, etc. (pas de code).
- `refactor:` : Refonte du code sans ajout/correction.
- `test:` : Ajout ou modification de tests.
- `chore:` : Mise à jour des dépendances, config système.


---

## PROCÉDURE DE ROLLBACK EN PRODUCTION (o2switch)

En cas d'échec après déploiement, voici la procédure manuelle complète.

### Rollback code (retour au commit précédent)
```bash
ssh user@votre-serveur.o2switch.net
cd /var/www/leopardo-rh-api

# 1. Activer la maintenance
php artisan down

# 2. Identifier le commit précédent
git log --oneline -5

# 3. Revenir au commit stable
git checkout <commit-hash-stable>

# 4. Réinstaller les dépendances du commit précédent
composer install --no-dev --optimize-autoloader --no-interaction

# 5. Vider les caches
php artisan config:clear && php artisan cache:clear && php artisan route:clear

# 6. Remettre en ligne
php artisan up
```

### Rollback base de données (restaurer le backup pre-migration)
```bash
# Les backups sont dans /var/backups/leopardo/ (créés par deploy.yml avant chaque migrate)

# Lister les backups disponibles
ls -lt /var/backups/leopardo/

# Restaurer (ATTENTION : écrase les données actuelles)
php artisan down

pg_restore \
  --clean \
  --if-exists \
  -U leopardo_user \
  -d leopardo_db \
  /var/backups/leopardo/leopardo_db_<TIMESTAMP>.dump

php artisan up
```

### Rollback migration Laravel uniquement
```bash
# Si seule la dernière migration a échoué (sans données perdues)
php artisan migrate:rollback --step=1
php artisan up
```

### Vérification post-rollback
```bash
# Vérifier que l'API répond correctement
curl -s https://api.leopardo-rh.com/api/v1/health | jq .

# Vérifier les logs
tail -50 storage/logs/laravel.log
```
