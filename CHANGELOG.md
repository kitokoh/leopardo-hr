# CHANGELOG — LEOPARDO RH
# Format : Keep a Changelog (keepachangelog.com)
# Versioning : Semantic Versioning (semver.org)

---

## [3.3.2] - 2026-03-31
### Rétrospection finale — Lacunes comblées avant top départ

#### Corrections de cohérence (audit final v3.3.2)
- **README.md** : version corrigée 3.1 → 3.3.2, compteur endpoints 70 → 82+
- **API contrats header** : « 70/70 » → « 82+/82+ », version 2.0 → 2.1
- **ARBORESCENCE_PROJET_COMPLET.md** : compteur endpoints 70 → 82+
- **Dart Project model** : `ProjectStatus` enum corrigé — suppression `paused`/`cancelled` (inexistants SQL), alignement sur `active|completed|archived`
- **22_ERREURS_ET_LOGS.md** v1.1 : ajout section complète « Stratégie de remplissage audit_logs » (Observer Eloquent, modèles observés, rétention 24 mois, exclusion champs sensibles)
- **22_ERREURS_ET_LOGS.md** v1.1 : ajout section « Gestion des timezones dans les calculs » (conversion UTC ↔ timezone entreprise, règles obligatoires pour développeurs, impact sur rapports)
- **CC-03 Module Pointage** : ajout règle §0 conversion timezone obligatoire avant toute comparaison métier (référence 22_ERREURS_ET_LOGS.md §4)

#### Endpoints manquants ajoutés dans API_CONTRATS (02_API_CONTRATS_COMPLET.md)
- **Profil employé** : `GET /profile`, `PUT /profile`, `POST /profile/photo`, `PUT /profile/password` (4 endpoints)
- **Notifications SSE** : `GET /notifications/stream` — Content-Type text/event-stream, note Nginx (1 endpoint)
- **Onboarding** : `GET /onboarding/status`, `POST /onboarding/complete` (2 endpoints)
- **Admin langues** : `GET /admin/languages`, `PUT /admin/languages/{id}` (2 endpoints)
- **Admin HR models** : `GET /admin/hr-models`, `GET /admin/hr-models/{country_code}` (2 endpoints)
- **Total API : 82+ endpoints** (était 70)

#### PayrollService corrigé (CC-03_A_CC-06_MODULES.md)
- Ajout étape 7 : `calculateLatePenalties()` avec règle plafond = 1 journée de salaire
- Retour de `penalty_deduction` dans le tableau de résultat (cohérence avec 05_REGLES_METIER.md §3)
- Numérotation des étapes mise à jour (9 → 11 étapes)

#### Modèle Dart corrigé (20_MODELES_DART_COMPLET.md)
- `AttendanceLog` : ajout de `sessionNumber` (int, défaut 1) + `fromJson` mis à jour

#### mock_payroll.json mis à jour
- `penalty_deduction: 0` ajouté dans `deductions` de chaque bulletin (cohérence avec PayrollService)

#### ORCHESTRATION_MAITRE.md mis à jour (v3.3.1)
- Date : 31 Mars 2026
- Index des fichiers : chemins réels de la structure actuelle (docs/dossierdeConception/...)
- Score documentation : 31/31 (était 23/23 — périmé)
- Checklist finale mise à jour

#### README.md
- Compte endpoints corrigé : 70 → 82+

---

## [3.3.1] - 2026-03-31
### Corrections bugs signalés par pair review

- **`CC-03_A_CC-06_MODULES.md`** : `$employee->salary_base` (supprimé le fantôme `gross_salary` — champ inexistant sur le modèle Employee)
- **`JU-01_A_JU-04_FLUTTER.md`** : `l10n/ (fr, ar, en, tr)` — turc, pas espagnol
- **`CC-01_INIT_LARAVEL.md`** : `LanguageSeeder (fr, ar, tr, en)` — supprimé `es` (espagnol non supporté)
- **`12_SECURITY_SPEC_COMPLETE.md`** : `national_id` rechiffré AES-256 via `EncryptedCast` (conformité légale RGPD / Loi 18-07 DZ / 09-08 MA)
- **`07_SCHEMA_SQL_COMPLET.sql`** : `session_number SMALLINT DEFAULT 1` + `UNIQUE(employee_id, date, session_number)` — support split-shift; commentaire chiffrement `national_id`
- **`04_architecture_erd/03_ERD_COMPLET.md`** : `session_number` ajouté dans `attendance_logs`, contrainte UNIQUE corrigée, annotation `national_id` chiffré, annotation `payrolls.gross_salary` ≠ `employees.salary_base`
- **`01_API_CONTRATS_COMPLETS/02_API_CONTRATS_COMPLET.md`** : 5 occurrences `gross_salary` → `salary_base` dans les endpoints Employee (POST /employees, GET /employees/{id}, PUT /employees, CSV import) — `gross_salary` conservé uniquement dans les réponses de bulletin de paie (correct)
- **Mocks attendance** : 3 fichiers JSON réécrits au format `sessions[]` cohérent avec le nouveau schéma `session_number` — exemple split-shift inclus (09/04)

---

## [3.3.0] - 2026-03-31
### Intégration pull ami — Specs manquantes critiques

#### Nouveaux fichiers
- **`08_multitenancy/09_TENANT_SERVICE_SPEC.md`** : implémentation complète `TenantService` en 7 étapes transactionnelles (création schéma, settings RH, planning par défaut, types d'absence, manager, user_lookups, email bienvenue) + méthode `purgeExpiredTenant` + 2 tests Pest
- **`07_securite_rbac/13_CHECK_SUBSCRIPTION_SPEC.md`** : spec `CheckSubscription` middleware — période de grâce 3 jours, header `X-Subscription-Grace`, crons `subscriptions:check`, `SubscriptionExpiredScreen` Flutter, 3 tests Pest
- **`20_templates_pdf/25_TEMPLATE_BULLETIN_PAIE.md`** : template Blade complet bulletin de paie (DomPDF), mentions légales par pays (NIF/RC/MF/Vergi No), support RTL arabe (police DejaVu Sans)
- **`20_templates_pdf/26_FORMATS_EXPORT_BANCAIRE.md`** : formats CSV/XML export virement bancaire par pays — DZ_GENERIC (Phase 1), MA_CIH, FR_SEPA ISO 20022, TN/TR génériques (Phase 2)

#### Fichiers enrichis
- **`.env.example`** (api/ + 15_CICD_ET_CONFIG/) : ajout `SUBSCRIPTION_GRACE_DAYS`, `SANCTUM_TOKEN_EXPIRATION_DAYS`, commentaires crons (subscriptions:check, leave:accrue, payroll:overtime, audit:purge), `TENANCY_DEFAULT_TYPE`
- **`09_tests_qualite/16_STRATEGIE_TESTS.md`** : ajout des tests `CreateTenantTest` et `SubscriptionTest` (5 scénarios supplémentaires)
- **`ARBORESCENCE_PROJET_COMPLET.md`** : mise à jour avec `20_templates_pdf/`, `SubscriptionService`, annotations des nouveaux fichiers
- **`README.md`** : 4 nouvelles sources de vérité ajoutées dans le tableau

---

## [3.2.0] - 2026-03-31
### Nettoyage et consolidation finale (pré-codage)

#### Suppressions (doublons et parasites)
- **`gestionemployer_CLEAN/`** : dossier export ZIP supprimé du repo (ne jamais mettre d'archives dans le repo)
- **`19_data/`** : dossier supprimé (contenait uniquement des doublons de 09, 10, 11)
- **`{backend,mobile,...},06_ORCHESTRATION}/`** : dossier au nom cassé supprimé
- **`04_architecture_erd/05_SEEDERS_ET_DONNEES_INITIALES.md`** : doublon supprimé (source de vérité = `18_schemas_sql/`)
- **`docs/17_MOCK_JSON/mock_*.json`** : 9 JSON supprimés (source de vérité = `mobile/assets/mock/`)
- **`docs/PROMPTS_EXECUTION/99_prompts_execution/PROMPT_MASTER_CLAUDE_CODE.md`** : doublon supprimé
- **`docs/dossierdeConception/15_CICD_ET_CONFIG/deploy.yml` + `tests.yml`** : doublons supprimés (source de vérité = `.github/workflows/`)

#### Corrections
- **ERD v2.0** : `tenancy_type` ajouté dans `companies`, `zkteco_id` dans `employees`, statut `active` dans `salary_advances`, table `user_lookups` complète avec politique de synchronisation
- **`docs/dossierdeConception/PROMPTcONCEPTion.md`** : archivé dans `00_docs/NOTES_CONCEPTION_INITIALES.md`

#### Ajouts
- **`docs/dossierdeConception/13_i18n/11_I18N_STRATEGIE_COMPLETE.md`** : stratégie i18n complète (fr/ar/en/tr, RTL Flutter + Vue.js, ARB, formats dates/montants, bulletins PDF multilingues)
- **`docs/dossierdeConception/14_glossaire/21_GLOSSAIRE_ET_DICTIONNAIRE.md`** : glossaire A-Z de 40+ termes métier et techniques
- **`docs/dossierdeConception/15_CICD_ET_CONFIG/README_CONFIG.md`** : clarification rôle du dossier vs `.github/workflows/`
- **`.gitignore`** : ajouté à la racine (exclut *.zip, *_CLEAN/, vendor/, build/, .env)

#### Mises à jour
- **`ARBORESCENCE_PROJET_COMPLET.md`** : réécrite en v3.1, reflète la structure réelle avec `api/`, `mobile/`, `docs/`
- **`README.md`** (racine) : réécrit, tableau des sources de vérité, ordre de démarrage
- **`docs/README.md`** : réécrit, périmé depuis migration vers docs/

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
