# 11 — Créer un Nouveau Module Métier

> **Quand l'utiliser :** Quand vous voulez ajouter un nouveau module complet au projet (backend + web + mobile).
> **Durée estimée :** Long (1-2h)
> **Prérequis :** Être sur `main` à jour, avoir le nom et la description du module

## Instructions

```
Agis en tant qu'architecte full-stack pour le projet Leopardo RH situé dans c:\Users\cheic\Downloads\gestionemployer.

Commence par lire AGENTS.md pour comprendre l'architecture modulaire.

Ton objectif est de créer un nouveau module métier complet nommé [NOM_MODULE]. Suis ces étapes dans l'ordre :

ÉTAPE 1 — BACKEND (api/)
- Crée le dossier api/app/Modules/[NomModule]/ avec la structure :
  - Controllers/
  - Models/
  - Services/
  - Infrastructure/
  - Requests/ (FormRequests pour la validation)
  - Events/
  - Listeners/
- Crée les migrations dans api/database/migrations/
- Crée les routes dans api/routes/modules/[nom_module].php
- Enregistre le module dans le ServiceProvider
- Ajoute les endpoints dans api/openapi.yaml
- Crée au moins un test fonctionnel dans api/tests/

ÉTAPE 2 — WEB (front/web/)
- Crée le layout dans front/web/src/app/(dashboard)/([nom_module])/layout.tsx
- Crée la page principale dans front/web/src/app/(dashboard)/([nom_module])/page.tsx
- Crée les types TypeScript dans front/web/src/modules/[nom_module]/types.ts
- Crée les composants dans front/web/src/modules/[nom_module]/components/

ÉTAPE 3 — MOBILE (front/mobile_apps/)
- Crée l'app Flutter front/mobile_apps/leopardo_[nom_module]/ avec `flutter create`
- Configure pubspec.yaml avec la dépendance leopardo_core (SDK >=3.3.0 <4.0.0)
- Initialise main.dart avec StartupGate
- Crée la structure features/[nom_module]/

ÉTAPE 4 — DOCUMENTATION
- Ajoute une entrée dans CHANGELOG.md
- Crée docs/modules/[NOM_MODULE]_MODULE_PLAN.md avec la description du module

ÉTAPE 5 — LIVRAISON
- Crée une branche feat/[nom_module]-scaffold
- Commit tout
- Push et crée une PR avec `Closes #<issue>` si une issue existe
- Vérifie les checks CI

Respecte les conventions :
- Backend : injection de dépendances, services avec interface, FormRequests
- Frontend : tokens premium glass-*, composants réutilisables, types TypeScript
- Mobile : StartupGate, requestWithRetry, extractDataList/extractDataMap, SDK >=3.3.0 <4.0.0
```

## Notes

- Remplacez [NOM_MODULE] par le nom réel du module (ex: Recrutement, Formation, Évaluation).
- Le module Marketing a été créé avec cette méthode : c'est le modèle à suivre.
- Chaque module doit avoir son propre ServiceProvider Laravel pour rester découplé.
