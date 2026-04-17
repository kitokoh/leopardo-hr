# RUNBOOK - DEPLOY PRODUCTION / STAGING
# Version 4.1.4 | 17 Avril 2026

## Principe
- **PR verte d'abord**
- **Merge sur `main` ensuite**
- **Deploy automatique seulement apres checks verts sur `main`**

## Preconditions
- Branch protection active sur `main`
- Auto-Deploy GitHub direct **desactive** dans Render pour eviter un deploy avant la fin des checks GitHub
- Checks GitHub obligatoires configures :
  - `Backend (PHP 8.4 + PostgreSQL 16 + Redis 7)`
  - `Backend Security (Composer Audit)`
  - `Mobile Flutter (Stable Channel)`
  - `Governance Gates (changelog + canonical files)`
  - `Dependency Review (PR Security)`
  - `CodeQL (Backend)`
- Secrets GitHub presents :
  - `RENDER_DEPLOY_HOOK_URL`
  - `FIREBASE_APP_ID`
  - `FIREBASE_TOKEN`
- `API_HEALTHCHECK_URL` optionnel mais recommande
- GitHub Security active :
  - Dependabot alerts
  - Dependabot security updates
  - Secret scanning
  - Push protection for secrets
  - Code scanning

## Flux normal
1. Ouvrir une PR vers `main`
2. Attendre les checks GitHub
3. Si tout est vert, merger la PR
4. Le push sur `main` relance `Tests - Leopardo RH`
5. Si `Tests - Leopardo RH` est vert sur `main`, `Deploy - Leopardo RH` se lance
6. GitHub :
   - declenche le deploy Render
   - attend le healthcheck API
   - construit puis distribue l'APK staging sur Firebase

## Ce qui est deploye automatiquement
- **API** : service Render
- **Front web manager** : inclus dans l'app Laravel Render
- **Mobile staging** : APK distribue sur Firebase App Distribution

## Verification post-deploy
- `GET /api/v1/health` repond `{"status":"ok", ...}`
- `/login` charge sans erreur 500
- login manager web OK
- dashboard web OK
- endpoints attendance OK
- PDF estimation OK
- build mobile visible dans Firebase App Distribution

## Cas ou le deploy ne part pas
- merge effectue alors qu'un check requis n'est pas vert
- `RENDER_DEPLOY_HOOK_URL` absent
- `FIREBASE_APP_ID` ou `FIREBASE_TOKEN` absent
- workflow `Tests - Leopardo RH` en echec sur `main`

## Rollback
- **API/Web** : rollback Render vers le dernier deploy sain
- **Mobile** : redistribuer la derniere version stable via workflow tag/manual

## Commande humaine minimale
- travail local
- push branche
- ouvrir PR
- merger uniquement quand GitHub est vert

Le reste doit etre automatique.
