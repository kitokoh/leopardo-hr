# 🐆 LEOPARDO RH — FICHIER D'ORCHESTRATION MAÎTRE
# Version 1.0 | Mars 2026
# Ce fichier est la SOURCE DE VÉRITÉ du projet
# À mettre à jour après CHAQUE tâche accomplie

---

## ⚡ ÉTAT ACTUEL DU PROJET

```
DATE DE MISE À JOUR : 29 Mars 2026
PHASE ACTUELLE      : PHASE 0 — Préparation finale avant code
PROCHAINE ACTION    : Initialiser les repos Git + lancer CC-01 et JU-01 en parallèle
```

---

## 📊 TABLEAU DE BORD — AVANCEMENT GLOBAL

| Module | Backend (Laravel) | Mobile (Flutter) | Web (Vue.js) | Tests |
|--------|:-----------------:|:----------------:|:------------:|:-----:|
| Infrastructure | ⬜ 0% | ⬜ 0% | ⬜ 0% | ⬜ |
| Auth | ⬜ 0% | ⬜ 0% | ⬜ 0% | ⬜ |
| Pointage | ⬜ 0% | ⬜ 0% | ⬜ 0% | ⬜ |
| Config Entreprise | ⬜ 0% | ⬜ 0% | ⬜ 0% | ⬜ |
| Absences | ⬜ 0% | ⬜ 0% | ⬜ 0% | ⬜ |
| Avances | ⬜ 0% | ⬜ 0% | ⬜ 0% | ⬜ |
| Tâches | ⬜ 0% | ⬜ 0% | ⬜ 0% | ⬜ |
| Paie | ⬜ 0% | ⬜ 0% | ⬜ 0% | ⬜ |
| Notifications | ⬜ 0% | ⬜ 0% | ⬜ 0% | ⬜ |
| Évaluations | ⬜ 0% | ⬜ 0% | ⬜ 0% | ⬜ |
| Rapports | ⬜ 0% | ⬜ 0% | ⬜ 0% | ⬜ |
| Super Admin | ⬜ 0% | — | ⬜ 0% | ⬜ |
| Landing Page | — | — | ⬜ 0% | — |
| CI/CD | ⬜ 0% | ⬜ 0% | — | ⬜ |

**Légende :** ⬜ Non démarré | 🔄 En cours | ✅ Terminé | ❌ Bloqué

---

## 📁 ÉTAT DE LA DOCUMENTATION

### Conception (Dossier `docs/dossierdeConception/`)

| Document | Statut | Version |
|---|---|---|
| 01_PROMPT_MASTER_CLAUDE_CODE.md | ✅ Complet | 1.0 |
| **02_API_CONTRATS_COMPLET.md** | ✅ **COMPLET** (70/70 endpoints) | **2.0 FINALE** |
| 03_ERD_COMPLET.md | ✅ Complet | 1.0 |
| 04_SPRINT_0_CHECKLIST.md | ✅ Complet | 1.0 |
| 05_SEEDERS_ET_DONNEES_INITIALES.md | ✅ Complet | 1.0 |
| 06_PROMPT_MASTER_JULES_FLUTTER.md | ✅ Complet | 1.0 |
| 07_SCHEMA_SQL_COMPLET.sql | ✅ Complet | 1.0 |
| 08_FEUILLE_DE_ROUTE.md | ✅ Complet | 1.1 |
| 09_REGLES_METIER_COMPLETES.md | ✅ Complet | 1.0 |
| 10_RBAC_COMPLET.md | ✅ Complet | 1.0 |
| 11_MULTITENANCY_STRATEGY.md | ✅ Complet | 2.0 |
| 12_SECURITY_SPEC_COMPLETE.md | ✅ Complet | 1.0 |
| 13_USER_FLOWS_VALIDES.md | ✅ Complet | 1.0 |
| 14_NOTIFICATION_TEMPLATES.md | ✅ Complet | 1.0 |
| 15_GUIDE_AJOUT_PAYS.md | ✅ Complet | 1.0 |
| 16_STRATEGIE_TESTS.md | ✅ Complet | 1.0 |
| 17_MOCK_DATA_MOBILE.md | ✅ Complet | 1.0 |
| 18_MARKETING_ET_VENTES.md | ✅ Complet | 1.0 |
| 19_CICD_ET_GIT.md | ✅ Complet | 1.0 |
| **20_MODELES_DART_COMPLET.md** | ✅ **COMPLET** (9 classes + enums) | **2.0 FINALE** |
| 21_GLOSSAIRE_ET_DICTIONNAIRE.md | ✅ Complet | 1.0 |
| 22_ERREURS_ET_LOGS.md | ✅ Complet | 1.0 |
| 23_STOCKAGE_ET_SAUVEGARDES.md | ✅ Complet | 1.0 |

**SCORE DOCUMENTATION : 23/23 documents ✅ — 100%**

### Fichiers de complétion (ce dossier)

| Fichier | Statut | Description |
|---|---|---|
| `01_API_CONTRATS_COMPLETS/02_API_CONTRATS_COMPLET.md` | ✅ Prêt | 70 endpoints documentés |
| `02_MODELES_DART/20_MODELES_DART_COMPLET.md` | ✅ Prêt | 9 classes Dart complètes |
| `03_MOCK_JSON/mock_auth_login.json` | ✅ Prêt | Mock POST /auth/login |
| `03_MOCK_JSON/mock_auth_me.json` | ✅ Prêt | Mock GET /auth/me |
| `03_MOCK_JSON/mock_attendance_today_A_not_checked.json` | ✅ Prêt | Mock today (pas pointé) |
| `03_MOCK_JSON/mock_attendance_today_B_checked_in.json` | ✅ Prêt | Mock today (pointé) |
| `03_MOCK_JSON/mock_attendance_history.json` | ✅ Prêt | 30 jours pointages |
| `03_MOCK_JSON/mock_absences.json` | ✅ Prêt | 4 absences variées |
| `03_MOCK_JSON/mock_tasks.json` | ✅ Prêt | 4 tâches variées |
| `03_MOCK_JSON/mock_payroll.json` | ✅ Prêt | 6 bulletins de paie |
| `03_MOCK_JSON/mock_notifications.json` | ✅ Prêt | 5 notifications |
| `04_CICD_ET_CONFIG/tests.yml` | ✅ Prêt | CI GitHub Actions |
| `04_CICD_ET_CONFIG/deploy.yml` | ✅ Prêt | CD GitHub Actions o2switch |
| `04_CICD_ET_CONFIG/.env.example` | ✅ Prêt | Variables d'env complètes |
| `04_CICD_ET_CONFIG/nginx-api.conf` | ✅ Prêt | Config Nginx o2switch |
| `04_CICD_ET_CONFIG/leopardo-horizon.supervisor.conf` | ✅ Prêt | Config Supervisor Horizon |
| `05_PROMPTS_EXECUTION/backend/CC-01_INIT_LARAVEL.md` | ✅ Prêt | Prompt init Laravel |
| `05_PROMPTS_EXECUTION/backend/CC-02_MODULE_AUTH.md` | ✅ Prêt | Prompt module Auth |
| `05_PROMPTS_EXECUTION/backend/CC-03_A_CC-06_MODULES.md` | ✅ Prêt | Prompts modules métier |
| `05_PROMPTS_EXECUTION/mobile/JU-01_A_JU-04_FLUTTER.md` | ✅ Prêt | Prompts Flutter |
| `05_PROMPTS_EXECUTION/frontend/CU-01_ET_AGENTS.md` | ✅ Prêt | Prompts Cursor + v0.dev + Manus |

---

## 🕐 CHRONOLOGIE — QUI FAIT QUOI QUAND

### 🔴 SPRINT 0 — MAINTENANT (avant le premier commit)

| # | Tâche | Agent | Durée | Statut |
|---|---|---|---|---|
| S0-1 | Créer compte Firebase + télécharger google-services.json | Toi (humain) | 30 min | ⬜ |
| S0-2 | Acheter domaine leopardo-rh.com | Toi (humain) | 15 min | ⬜ |
| S0-3 | Configurer sous-domaines DNS o2switch (api, app, admin) | Toi (humain) | 1h | ⬜ |
| S0-4 | Créer compte Google Play Developer ($25) | Toi (humain) | 30 min | ⬜ |
| S0-5 | Créer compte Apple Developer ($99/an) | Toi (humain) | 30 min | ⬜ |
| S0-6 | Copier `04_CICD_ET_CONFIG/tests.yml` → `.github/workflows/tests.yml` | Toi (humain) | 5 min | ⬜ |
| S0-7 | Ajouter les secrets GitHub (O2SWITCH_HOST, USER, SSH_KEY) | Toi (humain) | 15 min | ⬜ |
| S0-8 | Copier `04_CICD_ET_CONFIG/.env.example` → `api/.env.example` | Toi (humain) | 5 min | ⬜ |
| S0-9 | Copier les fichiers mock JSON → `mobile/assets/mock/` | Toi / Manus | 10 min | ⬜ |
| S0-10 | Initialiser le monorepo Git | Toi (humain) | 10 min | ⬜ |

### 🟠 SEMAINE 1 — Infrastructure parallèle

| # | Tâche | Agent | Prompt | Statut |
|---|---|---|---|---|
| W1-1 | Initialiser Laravel 11 + packages + PostgreSQL + Redis | Claude Code | CC-01 | ⬜ |
| W1-2 | Initialiser Flutter + GoRouter + Riverpod + mock auth | Jules | JU-01 | ⬜ |
| W1-3 | Vérifier les fichiers mock JSON | Manus | MA-01 | ⬜ |

### 🟡 SEMAINE 2 — Auth

| # | Tâche | Agent | Prompt | Statut |
|---|---|---|---|---|
| W2-1 | Module Auth backend complet | Claude Code | CC-02 | ⬜ |
| W2-2 | LoginScreen Flutter connecté au mock | Jules | JU-01 (suite) | ⬜ |

### 🟡 SEMAINE 3-4 — Modules Core

| # | Tâche | Agent | Prompt | Statut |
|---|---|---|---|---|
| W3-1 | Module Pointage backend | Claude Code | CC-03 | ⬜ |
| W3-2 | Module Config (depts, positions, schedules, sites) | Claude Code | CC-04 | ⬜ |
| W3-3 | Écran Pointage Flutter (avec mock) | Jules | JU-02 | ⬜ |
| W4-1 | Connexion API réelle Flutter | Jules | JU-03 | ⬜ |

### 🟢 SEMAINE 5-8 — Modules Métier

| # | Tâche | Agent | Prompt | Statut |
|---|---|---|---|---|
| W5-1 | Module Absences backend | Claude Code | CC-05 | ⬜ |
| W5-2 | Module Avances backend | Claude Code | CC-05 | ⬜ |
| W6-1 | Module Tâches + Projets backend | Claude Code | CC-06 | ⬜ |
| W6-2 | Écrans Absences + Tâches Flutter | Jules | JU-04 | ⬜ |
| W7-1 | Module Paie backend (PayrollService + PDF async) | Claude Code | CC-06 | ⬜ |
| W8-1 | Dashboard gestionnaire Vue.js | Cursor | CU-01 | ⬜ |

### 🔵 SEMAINE 9-12 — Finitions

| # | Tâche | Agent | Prompt | Statut |
|---|---|---|---|---|
| W9-1 | Module Notifications + Évaluations backend | Claude Code | — | ⬜ |
| W9-2 | Module Rapports + Super Admin backend | Claude Code | — | ⬜ |
| W10-1 | Écrans Paie + Notifications Flutter | Jules | JU-04 | ⬜ |
| W11-1 | Interface Super Admin Vue.js | Cursor | — | ⬜ |
| W12-1 | Tests E2E + bugfixes + optimisations | Tous | — | ⬜ |

### 🟣 SEMAINE 13-16 — Commercialisation

| # | Tâche | Agent | Prompt | Statut |
|---|---|---|---|---|
| W13-1 | Landing page HTML + Tailwind CSS | v0.dev | VO-01 | ⬜ |
| W13-2 | Intégration landing page dans Blade Laravel | Cursor | — | ⬜ |
| W14-1 | Configuration Nginx + Supervisor sur o2switch | Claude Code | — | ⬜ |
| W14-2 | Deploy.yml + premier déploiement prod | Toi + Claude Code | — | ⬜ |
| W15-1 | Tests utilisateurs (beta fermée) | Toi (humain) | — | ⬜ |
| W16-1 | Launch 🚀 | — | — | ⬜ |

---

## 🤖 RÉPARTITION DES AGENTS

| Agent | Propriétaire de | Ne touche JAMAIS à |
|---|---|---|
| **Claude Code** | `api/` (backend Laravel + Vue.js) | `mobile/` |
| **Jules** | `mobile/` (Flutter Android + iOS) | `api/` |
| **Cursor** | `api/resources/js/` (Vue.js frontend) | `mobile/`, architecture backend |
| **v0.dev** | Landing page HTML/CSS | Toute logique métier |
| **Manus** | Fichiers de données (JSON, YAML, MD) | Tout fichier de code source |
| **Claude.ai (toi ici)** | Documentation, conception, revue | — |

---

## 📋 JOURNAL DES DÉCISIONS TECHNIQUES

| Date | Décision | Raison | Impact |
|---|---|---|---|
| 2026-03-29 | Monorepo unique (api + mobile + docs) | Meilleure vision IA | Tous les agents dans un seul repo |
| 2026-03-29 | Multi-tenant hybride (schema + shared) | Optimisation ressources | TenantMiddleware gère les deux modes |
| 2026-03-29 | Riverpod 2.x UNIQUEMENT pour Flutter | Cohérence architecture | Pas de BLoC ni Provider legacy |
| 2026-03-29 | Inertia.js pour le web (pas d'API séparée) | Simplicité + SEO | Contrôleurs Laravel retournent des props Vue |
| 2026-03-29 | Landing page dans Laravel Blade | SEO + éviter un repo de plus | Accessible sur leopardo-rh.com/ |
| 2026-03-29 | PDF génération asynchrone (queue job) | Performance (25+ employés) | Retour job_id immédiat, polling status |
| 2026-03-29 | Horodatage serveur TOUJOURS | Sécurité + intégrité des données | Jamais de timestamp client accepté |

---

## 🚧 BLOCAGES CONNUS ET SOLUTIONS

| Blocage | Impact | Solution | Responsable |
|---|---|---|---|
| Pas encore de compte Firebase | Jules ne peut pas tester les push notifs | Utiliser mock FCM en dev, créer le compte avant W1 | Toi (humain) |
| Pas encore de domaine DNS | API non accessible en prod | Utiliser localhost en dev, acheter le domaine avant déploiement | Toi (humain) |
| Comptes stores mobiles non créés | Pas de distribution iOS/Android | Créer Google Play + Apple Developer avant W12 | Toi (humain) |

---

## 🔄 COMMENT METTRE À JOUR CE FICHIER

**Après chaque tâche accomplie :**

1. Changer le statut de ⬜ → 🔄 (en cours) ou ✅ (terminé) ou ❌ (bloqué)
2. Mettre à jour le tableau "Avancement Global" avec le % estimé
3. Mettre à jour la date de mise à jour en haut du fichier
4. Ajouter une entrée dans "Journal des décisions" si une décision technique a été prise
5. Ajouter dans "Blocages connus" si un problème est détecté

**Exemple de mise à jour après W1-1 :**
```
| Infrastructure | ✅ 100% | ⬜ 0% | ⬜ 0% | ✅ |
| Auth | ⬜ 0% | ⬜ 0% | ⬜ 0% | ⬜ |
```

---

## 🗂️ INDEX DES FICHIERS DU PROJET

### Documentation de conception (docs/dossierdeConception/)
```
01_PROMPT_MASTER_CLAUDE_CODE.md   → Règles absolues Claude Code
02_API_CONTRATS_COMPLET.md        → 70 endpoints documentés (SOURCE DE VÉRITÉ API)
03_ERD_COMPLET.md                 → Relations entre tables
04_SPRINT_0_CHECKLIST.md          → Setup o2switch, Firebase, stores
05_SEEDERS_ET_DONNEES_INITIALES.md → Données initiales (plans, langues, modèles RH)
06_PROMPT_MASTER_JULES_FLUTTER.md → Règles absolues Jules/Flutter
07_SCHEMA_SQL_COMPLET.sql         → Schéma PostgreSQL complet
08_FEUILLE_DE_ROUTE.md            → Planning 16 semaines
09_REGLES_METIER_COMPLETES.md     → Calculs paie, absences, pointage
10_RBAC_COMPLET.md                → Matrice permissions 7 rôles
11_MULTITENANCY_STRATEGY.md       → Architecture multi-tenant hybride
12_SECURITY_SPEC_COMPLETE.md      → Règles de sécurité
13_USER_FLOWS_VALIDES.md          → Flux utilisateurs validés
14_NOTIFICATION_TEMPLATES.md      → Templates push/email/SMS
15_GUIDE_AJOUT_PAYS.md            → Ajouter un nouveau pays
16_STRATEGIE_TESTS.md             → Tests obligatoires
17_MOCK_DATA_MOBILE.md            → Format mock data (ancien)
18_MARKETING_ET_VENTES.md         → Tunnel de vente
19_CICD_ET_GIT.md                 → Git flow + CI/CD
20_MODELES_DART_COMPLET.md        → 9 classes Dart (SOURCE DE VÉRITÉ FLUTTER)
21_GLOSSAIRE_ET_DICTIONNAIRE.md   → Termes du domaine
22_ERREURS_ET_LOGS.md             → Codes d'erreur standardisés
23_STOCKAGE_ET_SAUVEGARDES.md     → Stratégie de stockage
```

### Fichiers de complétion (leopardo-completion/)
```
01_API_CONTRATS_COMPLETS/         → Contrats API 70/70 endpoints
02_MODELES_DART/                  → Modèles Dart 9 classes
03_MOCK_JSON/                     → 9 fichiers mock pour Flutter
04_CICD_ET_CONFIG/                → tests.yml, deploy.yml, .env.example, nginx, supervisor
05_PROMPTS_EXECUTION/             → Prompts prêts à coller dans chaque IA
  backend/CC-01_INIT_LARAVEL.md
  backend/CC-02_MODULE_AUTH.md
  backend/CC-03_A_CC-06_MODULES.md
  mobile/JU-01_A_JU-04_FLUTTER.md
  frontend/CU-01_ET_AGENTS.md
06_ORCHESTRATION/                 → Ce fichier (ORCHESTRATION_MAITRE.md)
```

---

## ✅ CHECKLIST FINALE — CONCEPTION À 100%

- [x] 23 documents de conception complets
- [x] API contrats : 70/70 endpoints documentés
- [x] Modèles Dart : 9 classes + enums + copyWith + fromJson/toJson
- [x] Mock JSON : 9 fichiers couvrant tous les écrans Flutter
- [x] CI/CD : tests.yml + deploy.yml prêts à copier
- [x] .env.example : toutes les variables documentées
- [x] Nginx + Supervisor : configs prêtes pour o2switch
- [x] Prompts : CC-01 à CC-06, JU-01 à JU-04, CU-01, VO-01, MA-01 à MA-02
- [x] Orchestration : ce fichier = source de vérité du projet
- [ ] Monorepo Git initialisé (tâche humaine)
- [ ] Firebase configuré (tâche humaine)
- [ ] Domaine DNS o2switch configuré (tâche humaine)

**SCORE CONCEPTION : 10/13 — Les 3 restants sont des tâches humaines (comptes externes)**
