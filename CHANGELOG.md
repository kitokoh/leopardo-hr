# CHANGELOG — LEOPARDO RH
# Format : Keep a Changelog (keepachangelog.com)
# Versioning : Semantic Versioning (semver.org)

---

## [3.1.0] - 2026-03-30
### Corrections critiques (bugs bloquants)
- **SQL** `salary_advances.status` : ajout du statut `'active'` dans le CHECK constraint (était absent, causait un crash PayrollService)
- **SQL** `user_lookups` : renommage `user_id` → `employee_id` (alignement ERD ↔ SQL)
- **ERD** `employees` : suppression de `supervisor_id` (doublon de `manager_id`), ajout de `manager_id` et `zkteco_id` manquants
- **ERD** `employees` : remplacement de `is_active BOOL` par `status VARCHAR(20)` (alignement SQL)
- **ERD** `salary_advances` : renommage `repaid_amount` → `amount_remaining` (alignement SQL), ajout de `decision_comment`
- **Seeders** `PlanSeeder` : correction `excel_export: false → true` pour le plan Starter (source de vérité: modèle économique)
- **Seeders** `PlanSeeder` : ajout des features `evaluations` et `schema_isolation` dans les 3 plans
- **Dart** `AdvanceStatus` : ajout du statut `active` dans l'enum + getter `isActive` sur `SalaryAdvance`

### Ajouts manquants (avant démarrage du code)
- **API** : Endpoint `POST /public/register` (auto-onboarding Trial sans Super Admin) — spec complète avec payload, validations, comportement backend
- **API** : Endpoint `POST /devices/{id}/rotate-token` (rotation sécurisée des tokens ZKTeco)
- **API** : Réponse 403 `PLAN_EMPLOYEE_LIMIT_REACHED` sur `POST /employees`
- **Nouveau fichier** `07_securite_rbac/11_PLAN_LIMIT_MIDDLEWARE.md` — spec + implémentation Laravel + 3 tests Pest
- **Nouveau fichier** `11_ux_wireframes/24_ONBOARDING_GUIDE.md` — 4 étapes onboarding, API endpoints, composant Vue.js, séquence emails
- **Notifications** : Stratégie SSE (Server-Sent Events) pour notifications temps réel web — spec Laravel + Nginx + Vue.js composable
- **Stockage** : Décision tranchée Phase 1 = local (storage Laravel o2switch), Phase 2 = Cloudflare R2 — calcul taille estimée 8GB
- **deploy.yml** : Backup automatique PostgreSQL avant chaque `php artisan migrate --force`
- **CICD** `19_CICD_ET_GIT.md` : Procédure de rollback complète (code + DB + migration)
- **.env.example** : Variables ajoutées (`BACKUP_PATH`, `PLAN_LIMIT_ENABLED`, `SSE_*`, `ONBOARDING_TRIAL_DAYS`)
- **RTL** `CU-01_ET_AGENTS.md` : Stratégie support arabe RTL pour Vue.js/Inertia (Tailwind rtl:, dir HTML, Inertia shared data)
- **Multitenancy** `TenantMigrationService` : Version robuste avec transaction DB + rollback automatique + backup pre-migration + notification Super Admin en cas d'échec
- **Tests** `16_STRATEGIE_TESTS.md` : Tests ajoutés pour PlanLimit, PublicRegister, SSE, AdvanceStatus transitions

### Convention de commit Git (à appliquer dès le premier commit)
```
type(scope): description courte

Types : feat | fix | docs | test | chore | refactor | perf
Scopes : auth | employees | attendance | payroll | advances | absences | tasks | billing | tenant | ci | docs

Exemples :
feat(auth): add public registration endpoint with trial company creation
fix(payroll): add 'active' status to salary_advances CHECK constraint
docs(erd): unify manager_id and remove supervisor_id from employees
```

---

## [3.0.0] - 2026-03-20
### Ajouté
- Transition vers une architecture **Monorepo** regroupant `api/`, `mobile/` et `docs/`.
- Stratégie de **Multi-tenancy Hybride** (Isolation Schéma vs Shared Table).
- Table `user_lookups` dans le schéma public pour optimiser les performances de login.
- Documentation complète des **Workflows CI/CD** (GitHub Actions).
- Stratégie de **Cache Redis** détaillée.
- Pyramide de tests et document `16_STRATEGIE_TESTS.md`.
- Données de simulation JSON pour le mobile (`17_MOCK_DATA_MOBILE.md`).
- Définition du tunnel de vente et landing page (`18_MARKETING_ET_VENTES.md`).
- Modèles Dart pour Flutter (`20_MODELES_DART.md`).
- Complétion de 100% des contrats d'API (`02_API_CONTRATS.md`).

### Corrigé
- Suppression de la contrainte unique sur `attendance_logs` pour supporter les split-shifts.
- Ajout du `zkteco_id` dans la table `employees` pour la biométrie.

---

## [2.0.0] - 2026-03-10
### Ajouté
- Documentation RBAC complète (7 rôles).
- Spécifications de sécurité (Sanctum tokens opaques).
- User Flows détaillés.
- Guide d'ajout de nouveaux pays.

---

## [1.0.0] - 2026-02-15
### Ajouté
- Initialisation de la conception technique.
- Premier ERD et schéma SQL de base.
- Structure initiale des dossiers.
