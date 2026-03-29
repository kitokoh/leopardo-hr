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
