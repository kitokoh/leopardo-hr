# Stratégie CI/CD et Processus Git (Render + Neon)
# DOCUMENT_VERSION = 2.1.0 | 12 Avril 2026

Ce document définit comment le code passe du développement local à la production.

## 1. Modèle de Branches Git

- **`main`** : Branche de production. Tout ce qui est sur `main` est automatiquement déployé sur Render.
- **`develop`** : Branche d'intégration. Utilisée pour fusionner les fonctionnalités avant la mise en production.
- **`feat/xxx`** ou **`mvp-xx`** : Branches de travail pour les nouvelles fonctionnalités.

## 2. Automatisation (GitHub Actions)

### Workflow : Tests Automatisés (`tests.yml`)
- **Déclencheur** : Chaque Pull Request vers `develop` ou `main`.
- **Contenu** :
  1. Build de l'environnement Laravel (PHP 8.4).
  2. Création d'une base PostgreSQL de test.
  3. Exécution de `php artisan test`.
  4. Build et analyse Flutter (`flutter analyze`).
- **Objectif** : Garantir que rien n'est cassé avant la fusion.

## 3. Déploiement en Production (CD)

Le déploiement est **automatisé** via l'intégration native de Render.

### Flux de déploiement :
1. Le développeur fusionne une PR valide dans **`main`**.
2. GitHub notifie **Render**.
3. **Render** lance la construction de l'image Docker (`Dockerfile.prod`).
4. À la fin du build, Render exécute automatiquement les **migrations** de base de données (vers Neon) et démarre le nouveau conteneur.
5. **Downtime** : 0 seconde (Zero-downtime deployment).

## 4. Procédure de Rollback

En cas de problème en production sur Render :
1. Allez dans le dashboard **Render**.
2. Sélectionnez le service `leopardo-api`.
3. Allez dans **"Events"** ou **"Deploy"**.
4. Sélectionnez le déploiement précédent valide et cliquez sur **"Rollback to this deploy"**.

## 5. Accès et Logs

- **Logs Production** : Directement consultables dans l'interface Render (onglet Logs).
- **Accès DB** : Via le dashboard Neon (ou tout client SQL avec l'URL sécurisée).

---
> [!NOTE]
> L'ancienne procédure basée sur o2switch et SSH a été supprimée au profit de cette architecture Cloud native plus robuste et simple.
