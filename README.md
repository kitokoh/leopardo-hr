# LEOPARDO RH — Guide du projet
## SaaS RH multilingue pour PME africaines et méditerranéennes
**Version 3.3.3 | Mars 2026 | Statut : Prêt pour le codage — ~100%**

---

## STRUCTURE DU MONOREPO

```
leopardo-rh/
├── api/                    ← BACKEND Laravel 11 + Vue.js/Inertia (à initialiser avec CC-01)
├── mobile/                 ← MOBILE Flutter (à initialiser avec JU-01)
│   └── assets/mock/        ← Données JSON pour dev sans API réelle
├── .github/workflows/      ← CI/CD GitHub Actions (deploy.yml + tests.yml)
└── docs/                   ← DOCUMENTATION (source de vérité)
    ├── dossierdeConception/ ← Specs techniques et fonctionnelles
    └── PROMPTS_EXECUTION/  ← Prompts agents IA (Claude Code, Jules)
```

---

## SOURCES DE VÉRITÉ — Références obligatoires avant de coder

| Quoi | Fichier |
|------|---------|
| **Contrats API** (82+ endpoints) | `docs/dossierdeConception/01_API_CONTRATS_COMPLETS/02_API_CONTRATS_COMPLET.md` — Source de vérité |
| **OpenAPI/Swagger** (76 endpoints) | `api/openapi.yaml` — Spécification machine-readable |
| **ERD** v2.0 | `docs/dossierdeConception/04_architecture_erd/03_ERD_COMPLET.md` |
| **Schéma SQL** PostgreSQL complet | `docs/dossierdeConception/18_schemas_sql/07_SCHEMA_SQL_COMPLET.sql` |
| **Règles métier** (paie, pointage, absences) | `docs/dossierdeConception/05_regles_metier/05_REGLES_METIER.md` |
| **Multitenancy** (shared vs schema) | `docs/dossierdeConception/08_multitenancy/08_MULTITENANCY_STRATEGY.md` |
| **RBAC** (7 rôles + permissions) | `docs/dossierdeConception/07_securite_rbac/10_RBAC_COMPLET.md` |
| **Sécurité** (Sanctum, chiffrement, brute force) | `docs/dossierdeConception/07_securite_rbac/07_SECURITE_COMPLETE.md` |
| **i18n** (fr/ar/en/tr, RTL) | `docs/dossierdeConception/13_i18n/11_I18N_STRATEGIE_COMPLETE.md` |
| **TenantService** (7 étapes transactionnelles) | `docs/dossierdeConception/08_multitenancy/09_TENANT_SERVICE_SPEC.md` |
| **CheckSubscription** (grâce, suspension, purge) | `docs/dossierdeConception/07_securite_rbac/13_CHECK_SUBSCRIPTION_SPEC.md` |
| **Bulletin de paie** (template Blade + DomPDF) | `docs/dossierdeConception/20_templates_pdf/25_TEMPLATE_BULLETIN_PAIE.md` |
| **Export bancaire** (DZ/MA/FR/TN/TR) | `docs/dossierdeConception/20_templates_pdf/26_FORMATS_EXPORT_BANCAIRE.md` |
| **Modèles Dart** Flutter | `docs/dossierdeConception/16_MODELES_DART/20_MODELES_DART_COMPLET.md` |
| **Mocks JSON** mobile | `mobile/assets/mock/` |

---

## DOCUMENTATION — Dossier par dossier

| Dossier | Contenu |
|---------|---------|
| `00_docs/` | CDC et DCT d'origine (PDF/DOCX) |
| `00_vision_marche/` | Vision produit, analyse marché, concurrents |
| `01_API_CONTRATS_COMPLETS/` | 82+ endpoints documentés — source de vérité API |
| `02_personas/` | Personas et User Stories |
| `03_modele_economique/` | Plans tarifaires (Trial/Starter/Business/Enterprise) |
| `04_architecture_erd/` | ERD v2.0 + Seeders données initiales |
| `05_regles_metier/` | Règles métier complètes + Guide ajout pays |
| `07_securite_rbac/` | Sécurité, RBAC, PlanLimitMiddleware |
| `08_multitenancy/` | Stratégie hybride shared/schema |
| `09_tests_qualite/` | Stratégie de tests + Erreurs et logs |
| `10_deploiement_cicd/` | CI/CD, Git workflow, sauvegardes |
| `11_ux_wireframes/` | Wireframes HTML + User Flows + Guide onboarding |
| `12_notifications/` | Templates push/email + stratégie SSE |
| `13_i18n/` | Stratégie i18n complète (fr/ar/en/tr + RTL) |
| `14_glossaire/` | Glossaire et dictionnaire technique |
| `19_diagrammes_uml/` | 9 diagrammes UML Mermaid (classe, séquence, state machines, use case, déploiement) |
| `15_CICD_ET_CONFIG/` | Fichiers config opérationnels (nginx, supervisor, env) |
| `16_MODELES_DART/` | Classes Dart Flutter complètes |
| `17_MOCK_JSON/` | Documentation des mocks (les fichiers sont dans `mobile/assets/mock/`) |
| `18_schemas_sql/` | Schéma SQL PostgreSQL + Seeders |

---

## PROMPTS D'EXÉCUTION — Pour les agents IA

| Prompt | Agent | Description |
|--------|-------|-------------|
| `ORCHESTRATION/ORCHESTRATION_MAITRE.md` | Tous | **Tableau de bord global** — mettre à jour après chaque tâche |
| `api/openapi.yaml` | Claude Code / Swagger UI | **Spec OpenAPI 3.0** — 76 endpoints machine-readable |
| `backend/CC-01_INIT_LARAVEL.md` | Claude Code | Initialisation Laravel 11 |
| `backend/CC-02_MODULE_AUTH.md` | Claude Code | Module Auth + Sanctum |
| `backend/CC-03_A_CC-06_MODULES.md` | Claude Code | Modules métier (pointage, absences, paie…) |
| `mobile/JU-01_A_JU-04_FLUTTER.md` | Jules | App Flutter complète |
| `frontend/CU-01_ET_AGENTS.md` | Claude | Frontend Vue.js + Landing page |
| `99_prompts_execution/01_PROMPT_MASTER_CLAUDE_CODE.md` | Claude Code | Prompt master complet |

---

## DÉMARRAGE

```bash
# 1. Backend
cd api
composer create-project laravel/laravel . --prefer-dist
# → Lire et exécuter : docs/PROMPTS_EXECUTION/backend/CC-01_INIT_LARAVEL.md

# 2. Mobile
cd mobile
flutter create . --org com.leopardo --project-name leopardo_rh
# → Lire et exécuter : docs/PROMPTS_EXECUTION/mobile/JU-01_A_JU-04_FLUTTER.md
```

---

## RÈGLES DU PROJET

| Règle | Valeur |
|-------|--------|
| Langue principale | Français (`fr`) — fallback i18n |
| Stack Backend | Laravel 11 · PostgreSQL 16 · Redis · Sanctum |
| Stack Mobile | Flutter 3.x · Riverpod · Dio |
| Stack Frontend | Vue.js 3 · Inertia.js (servi par Laravel) |
| Multitenancy défaut | `shared` (Trial/Starter/Business) |
| Multitenancy Enterprise | `schema` (isolation physique PostgreSQL) |
| Auth mobile | Tokens Sanctum opaques — 90 jours |
| Auth web SPA | Cookie httpOnly SameSite=Strict — 8h |
