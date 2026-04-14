# 🌳 ARBORESCENCE COMPLÈTE DU PROJET LEOPARDO RH
# DOCUMENT_VERSION = 4.1.2 | 12 Avril 2026

Ce document présente la structure réelle et attendue du monorepo après consolidation.

```text
leopardo-rh/                          ← Racine du monorepo (Gestion Employer)
├── .github/
│   └── workflows/
│       └── tests.yml                 ← Tests automatisés API + Mobile (CI)
├── .gitignore                        ← Exclut vendor/, build/, .env, etc.
├── api/                              ← BACKEND Laravel 11
│   ├── app/                          ← Cœur de l'application (Models, Controllers, Services)
│   ├── config/                       ← Configuration (database.php, app.php)
│   ├── database/                     ← Migrations, Seeders, Factories
│   ├── docker/                       ← Fichiers Docker locaux (php84/)
│   ├── public/                       ← Point d'entrée HTTP public
│   ├── resources/                    ← Vues Blade (Dashboard), Langues, Assets CSS/JS
│   ├── routes/                       ← Définition des APIs (api.php) et Web (web.php)
│   ├── tests/                        ← Suite de tests (Unit, Feature)
│   ├── Dockerfile.prod               ← Config Production (FrankenPHP pour Render)
│   ├── docker-compose.yml            ← Orchestration locale
│   └── start-local.ps1               ← Script de démarrage rapide local
├── mobile/                           ← MOBILE Flutter
│   ├── assets/                       ← Images, Fonts, Mocks JSON
│   ├── lib/                          ← Code source Dart (Clean Architecture)
│   │   ├── core/                     ← Clients API, Thèmes, Erreurs
│   │   ├── features/                 ← Dossiers par fonctionnalité (Auth, Attendance)
│   │   └── models/                   ← Modèles de données désérialisables
│   ├── test/                         ← Tests unitaires et widget Dart
│   └── pubspec.yaml                  ← Dépendances Flutter
├── docs/                             ← DOCUMENTATION (Source de Vérité)
│   ├── dossierdeConception/          ← Specs détaillées (par module 01 à 20)
│   ├── GESTION_PROJET/               ← Guides, Index, Garde-fous, Setup
│   └── PROMPTS_EXECUTION/            ← Historique des prompts v2 et v3 (MVP)
├── PILOTAGE.md                       ← TABLEAU DE BORD UNIQUE (Master)
├── CHANGELOG.md                      ← Journal des modifications
├── JOURNAL_RACINE.md                 ← Historique des sessions IA
├── DEMARRAGE_RAPIDE.md               ← Comment lancer le projet en 2 min
└── README.md                         ← Présentation générale du projet
```

## NOTES IMPORTANTES

### Ce qui N'est PAS dans ce repo (généré à l'init)
- `api/vendor/` — généré par `composer install`
- `api/node_modules/` — généré par `npm install`
- `mobile/.dart_tool/` — généré par `flutter pub get`
- `mobile/build/` — généré par `flutter build`
- `api/.env` — jamais commité (utiliser les secrets Render en production)

### Infrastructure de Déploiement
- **Target** : Render (App) + Neon (DB)
- **CI** : GitHub Actions (`tests.yml`) sur chaque PR vers `main`.
- **CD** : Déploiement automatique par Render sur détection de push dans `main`.
