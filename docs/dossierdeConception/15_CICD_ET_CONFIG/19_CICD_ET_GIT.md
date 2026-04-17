# Strategie CI/CD et Processus Git (Render + Neon + Firebase)
# DOCUMENT_VERSION = 2.2.0 | 17 Avril 2026

Ce document definit le flux simple a respecter pour aller vite sans casser l'application.

## 1. Mode de travail Git

- **`main`** : branche deployable. Rien ne part en deploiement sans etre merge sur `main`.
- **`develop`** : branche d'integration optionnelle pour lots intermediaires.
- **`codex/*` / `feat/*` / `fix/*`** : branches de travail.

## 2. Validation automatique sur Pull Request

Le workflow `.github/workflows/tests.yml` tourne sur chaque PR vers `main` ou `develop`.

### Checks executes
1. **Backend**
   - validation `composer.json`
   - install dependencies
   - migrations PostgreSQL de test
   - `php artisan test`
2. **Backend Security**
   - `composer audit --locked --no-dev`
3. **Mobile**
   - `flutter pub get`
   - verification format Dart
   - `flutter analyze`
   - `flutter test`
   - build Android smoke (`flutter build apk --debug`)
4. **Governance**
   - verification des fichiers canoniques
   - verification `CHANGELOG.md` sur scope critique
5. **Dependency Review**
   - blocage des dependances vulnerables ajoutees en PR
6. **CodeQL**
   - analyse statique de securite backend (PHP)
   - execution sur PR, `main`, et scan planifie hebdomadaire

### Regle
- **Aucune PR ne doit etre mergee tant que ces checks ne sont pas verts.**

## 3. Ce qui se passe apres merge sur `main`

Le merge sur `main` declenche d'abord a nouveau `tests.yml`.

Si ce workflow termine en **success** sur l'evenement `push` de `main`, alors le workflow `.github/workflows/deploy-main.yml` se lance automatiquement.

### Deploiement automatique
1. **API + Web manager**
   - GitHub appelle le **Render Deploy Hook**
   - Render rebuild l'image Docker de `api/`
   - Render relance l'application
   - GitHub attend ensuite le healthcheck `/api/v1/health`
2. **Mobile staging**
   - build APK release automatique
   - distribution Firebase App Distribution vers le groupe `testeurs-internes`

> Note : le **front web manager** est livre par Laravel/Blade dans `api/`, donc il est deploie en meme temps que l'API Render.

## 4. Production mobile

Le workflow `.github/workflows/mobile-distribute.yml` reste disponible pour :
- **tags** `v*-staging`
- **tags** `v*-prod`
- **workflow_dispatch** manuel

Usage recommande :
- `main` => distribution **staging** automatique aux testeurs
- `tag prod` => build de release de production

## 5. Secrets GitHub obligatoires

### Pour l'API / Web
- `RENDER_DEPLOY_HOOK_URL`
- `API_HEALTHCHECK_URL` *(optionnel, sinon fallback sur `https://gestionemployerbackend.onrender.com/api/v1/health`)*

> Important : pour respecter ce flux, **desactiver l'Auto-Deploy Render branche GitHub** et utiliser uniquement le deploy hook. Sinon Render peut deployer avant la fin des checks GitHub sur `main`.

### Pour le mobile
- `FIREBASE_APP_ID`
- `FIREBASE_TOKEN`

## 6. Politique simple a retenir

- **Commit local** : tu peux tester si tu veux, mais ce n'est plus le seul filet de securite.
- **Pull Request** : GitHub verifie qualite, fonctionnalites et securite.
- **Merge sur `main`** : GitHub revalide puis deploie automatiquement.
- **Si un check echoue** : pas de merge, pas de deploiement.

## 7. Garde-fous GitHub a activer dans l'interface

- **Dependabot alerts**
- **Dependabot security updates**
- **Secret scanning**
- **Push protection for secrets**
- **Code scanning** (pour exposer les resultats CodeQL)

## 8. Rollback

- **API/Web** : rollback depuis Render vers le deploy precedent valide.
- **Mobile** : redistribuer le build precedent depuis Firebase App Distribution ou relancer un tag stable.

---
> Objectif de ce flux : **un seul geste humain utile** = merger une PR verte. Le reste doit etre automatique.
