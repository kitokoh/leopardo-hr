# Conventions de Code — Leopardo RH

> Ce document definit les standards de code obligatoires pour tout contributeur (humain ou IA).
> Derniere mise a jour : 2026-08-17 (revue PM — réconciliation README/ARCHITECTURE/PILOTAGE)

---

## 1. Structure du projet

```
leopardo-hr/
├── api/                    # Backend Laravel (PHP 8.4+)
├── front/
│   ├── admin-dashboard/    # Dashboard admin (Vue.js / Vite)
│   ├── web/                # Vitrine (Next.js)
│   └── mobile_apps/        # Apps Flutter : leopardo_core, leopardo_employee, leopardo_manager, leopardo_hr, leopardo_marketing, leopardo_platform_admin
├── docs/                   # Documentation technique
├── .github/workflows/      # CI/CD GitHub Actions
├── docker-compose.yml      # Dev environment
└── AGENTS.md               # Guide operationnel agents IA
```

## 2. PHP / Laravel (Backend)

### 2.1 Style general

- **PHP 8.4 minimum** — utiliser les fonctionnalites modernes (enums, readonly, named args, match)
- **`declare(strict_types=1);`** en haut de chaque fichier PHP (sauf config)
- **Namespace PSR-4** — `App\Modules\<NomModule>\*`, `App\Core\*`, `App\Shared\*`
  _(Les anciens espaces `App\Http\Controllers\Api\V1\*` et `App\Services\*` sont supprimés — voir `api/ARCHITECTURE.md`)_
- **PHPStan** — `phpstan.neon` declare `level: max` pour l'ensemble de `app/` + `routes` + `tests`. Ce que la CI verifie, de facon bloquante :
  - `phpstan-modules.neon` (niveau 5, `app/Core`/`app/Modules`/`app/Shared`) — job `phpstan-modules` (bloquant).
  - `phpstan-strict.neon` (niveau 8, meme perimetre) — job `phpstan-strict`, bloquant sur le **delta** uniquement depuis #1413 : `phpstan-strict-baseline.neon` gele les ~2950 erreurs pre-existantes (voir `api/ARCHITECTURE.md` section "Trajectoire PHPStan" pour la repartition par module et la trajectoire de reduction), toute nouvelle erreur hors baseline fait echouer la CI.
  - `phpstan.neon` (`level: max`, perimetre app/routes/tests) — branche dans le job `backend-quality` de `tests.yml` (issue #5590) : le step PHPStan analyse TOUT fichier PHP modifie de `app/`, `routes/` et `tests/` au niveau max (avant #5590 : seuls `app/AI`, `app/Http/Middleware` et `routes` etaient couverts). Gate sur le delta via `phpstan-baseline.neon` : les erreurs pre-existantes sont gelees, toute erreur NOUVELLE sur un fichier touche fait echouer la CI. Regenerer la baseline via le workflow `phpstan-baseline.yml` apres un chantier important (elle doit rester proche de `main`).
- **Pas de `Any`, `mixed` sauf absolument necessaire** — typer tous les parametres et retours

### 2.2 Nommage

| Element | Convention | Exemple |
|---------|-----------|---------|
| Modeles | PascalCase, singulier | `PayrollRun`, `Employee`, `VehicleTrip` |
| Controllers | PascalCase + `Controller` | `PayrollRunController` |
| Policies | PascalCase + `Policy` | `PayrollPolicy` |
| Services | PascalCase + `Service` | `PayrollCalculator`, `TraccarService` |
| Events | PascalCase, passe compose | `PayrollValidated`, `EmployeeCreated` |
| Migrations | snake_case avec timestamp | `2026_05_10_100001_create_payroll_engine_tables.php` |
| Tests | PascalCase + `Test` | `PayrollRunControllerTest` |
| Routes | kebab-case, pluriel | `/api/v1/payroll-runs`, `/api/v1/employees` |
| Colonnes DB | snake_case | `company_id`, `created_at`, `is_active` |

### 2.3 Architecture API

```
app/
├── Modules/<Nom>/              # Monolithe modulaire DDD (18 modules actifs)
│   ├── Application/            # Actions, DTOs, Queries (orchestration)
│   ├── Domain/                 # Models, Contracts, Exceptions (règles métier)
│   ├── Infrastructure/         # Services, Repositories (implémentation)
│   ├── Interfaces/Api/V1/      # Controllers + Requests (HTTP)
│   └── Providers/              # ServiceProvider du module
├── Core/                       # Socle transversal : Auth, Tenant, Feature
├── Shared/                     # Code partagé (traits, enums, helpers)
└── Http/
    ├── Middleware/             # Middlewares HTTP
    └── Resources/Api/V1/       # JsonResource centralisées (dérogation PA2-ARCH-010)
```

Modules actifs : `Absence`, `Accounting`, `Attendance`, `Billing`, `Cabinet`, `Cameras`, `EdgeSync`, `Expense`, `Fleet`, `Growth`, `HR`, `Marketing`, `Notification`, `Onboarding`, `Payroll`, `Planning`, `Platform`, `Recruitment` — état couche-par-couche dans `docs/ARCHITECTURE_STATUS.md`.
> `app/Http/Controllers/Api/V1/`, `app/Models/` et `app/Services/` ont été **supprimés** (PR #824, phase 2, #1728) — tout nouveau code va dans `Modules/<Nom>/` (`App\Modules\<Nom>\*`).

### 2.4 Multi-tenant

- **Tout modele metier DOIT avoir `company_id`** — utiliser le trait `BelongsToCompany`
- **Global Scope** — les queries sont automatiquement filtrees par `company_id`
- **Les modeles sans `company_id`** (ex: `Language`, `Country`) sont isoles via relation parent + `whereHas()`
- **Middleware `TenantMiddleware`** — doit garder `try/finally` autour de `resetToPrevious()`
- **Seeders** — toujours attacher un `company_id` aux donnees de test

### 2.5 RBAC & Policies

- **Chaque action controller DOIT passer par une Policy Laravel**
- Policies enregistrees explicitement dans `AppServiceProvider`
- Matrice RBAC documentee dans `docs/security/RBAC_ROUTE_MATRIX.md`
- Roles standard : `super_admin`, `company_admin`, `hr_manager`, `manager`, `employee`

### 2.6 Migrations

- **Idempotentes** — utiliser `Schema::hasTable()` + `try/catch` erreur PostgreSQL `42P07`
- **PostgreSQL only** — ne pas utiliser de syntaxe MySQL-specifique
- Champs obligatoires : `id`, `company_id` (sauf modeles globaux), `created_at`, `updated_at`

D
#### Règle search_path — migrations tenant (#1613, bug F-17 #1595)

`Schema::hasTable('x')` / `Schema::table('x', ...)` interrogent **`current_schema()` uniquement**,
alors que `DB::table('x')` résout via le **search_path** de la session. Selon le contexte
(CI `DB_SEARCH_PATH=shared_tenants` ; phpunit après `migrate:fresh` où `public/0001` pose
`SET search_path TO public`), la même table peut vivre dans `public` ou `shared_tenants` —
le garde répond alors `false` à tort et un backfill/ALTER est **silencieusement sauté**
(cause racine du backfill F-17 non exécuté, corrigé en 2026-08-09).

**Les migrations tenant doivent résoudre le schéma de la table via `current_schemas(false)`
(même ordre que la résolution `DB::table`), pas via `current_schema()` ni un nom nu :**

```php
// ✅ Pattern imposé (migrations 2026_08_09_000003/000004)
private function resolveTableSchema(string $table): ?string
{
    $row = DB::selectOne("
        SELECT t.table_schema
        FROM information_schema.tables t
        WHERE t.table_name = ?
          AND t.table_schema = ANY (current_schemas(false))
        ORDER BY array_position(current_schemas(false), t.table_schema)
        LIMIT 1
    ", [$table]);

    return $row ? (string) $row->table_schema : null;
}
```

- Utiliser `Schema::table("{$schema}.{$table}", ...)` (préfixe schéma) pour les ALTER.
- Utiliser `DB::table($table)` (résolution search_path) pour les lectures/écritures.
- Un garde `if ($schema === null) { return; }` remplace `Schema::hasTable()`.


### 2.7 DDD pour nouveaux modules

Les **nouveaux modules** suivent la structure DDD (template dans `stubs/module-template/`) :

```
Domain/         # Entites, Value Objects, Repository Interfaces, Domain Events
Application/    # Use Cases, DTOs, Command/Query handlers
Infrastructure/ # Implementations Repository, Integrations externes
Interfaces/     # Controllers, Requests (Resources : voir derogation PA2-ARCH-010 ci-dessous)
```

Les modules existants (pre-sprint) gardent la structure Laravel classique.

> **Derogation documentee — API Resources centralisees (PA2-ARCH-010)** : les classes `JsonResource` restent **centralisees** dans `app/Http/Resources/Api/V1/`, y compris pour les nouveaux modules, plutot que placees dans `Interfaces/Api/V1/Resources/` de chaque module. Raison : plusieurs Resources sont partagees entre modules (ex. `LoanResource` par `HR`+`Payroll`), et les placer dans le module createur forcerait les autres modules consommateurs a faire un import inter-module, ce qui viole l'interdiction §2.7/§2.3 (« un module n'importe jamais directement les classes d'un autre module »). Voir `api/ARCHITECTURE.md` pour le detail. Une Resource strictement interne a un seul module et jamais partagee peut exceptionnellement rester dans `Interfaces/Api/V1/Resources/` du module.


### 2.9 Convention des verbes HTTP API (issue #4930)

Une seule règle, appliquée partout :

- **POST** → créer une ressource **et** déclencher une action métier (approve/reject/disburse/validate/activate/publish…).
- **PUT/PATCH** → modifier une ressource (PATCH pour mise à jour partielle d'état de la ressource elle-même).
- **GET** → lecture seule, sans effet de bord. Les générations/exports idempotents (pdf, csv) sont tolérés mais doivent être documentés comme tels.

État actuel (2026-08-18, chantier de convergence en cours) :
- Notifications harmonisées en PUT (#2674/#2955/#3635).
- Approbations biométrie/approvals/contrats/annonces : POST ✅ ; absences/loans/expense-claims/corrections : PUT **à migrer en POST** (rétrocompatibilité clients Flutter à vérifier avant changement — cf. issue #4930).
- `PATCH /onboarding-setup/{stepKey}/complete|skip` : actions d'état → à passer en POST avec migration client (issue #4930).
- `PUT /payrolls/{id}/validate` (legacy) vs `POST /payroll-runs/{id}/validate` : seul `payroll-runs` est canonique.

Règle de migration : ne jamais changer un verbe sans vérifier les clients (apps Flutter, admin, web) ; ajouter d'abord le nouveau verbe en parallèle, déprécier l'ancien (commentaire de route + OpenAPI), puis supprimer après transition.


### 2.10 Unicité des routes API (issue #4932)

- Un concept métier = **un seul chemin canonique** + au plus un alias de compatibilité, marqué `// DÉPRÉCIÉ` (commentaire de route) avec la cible canonique.
- Doublons connus à déprécier (2026-08-18) : `POST /notifications/mark-all-read` (→ `POST /notifications/read-all`), `/social-account` (→ `/social-accounts`), `/posts` (→ `/social-posts`), ressource legacy `payrolls` (→ `payroll-runs`), `GET /hr/employees` (→ `GET /employees`), double namespace super-admin `/platform/*` vs `/admin/*` (décision à acter).
- **Throttle : ne pas re-déclarer `throttle:api` dans les groupes internes** — le groupe `api` par défaut de Laravel 12 (via `withRouting(api:)`) l'applique déjà ; une re-déclaration consomme le compteur deux fois par requête (limite effective divisée par deux). Vérifier avec `php artisan route:list` avant de retirer les déclarations existantes (issue #4932, point 7).

### 2.8 i18n

- **Utiliser `__()` ou `trans()`** — jamais de chaines hardcodees en francais/anglais
- 4 langues supportees : FR, EN, AR, TR
- Support RTL pour l'arabe
- Fichiers de traduction dans `resources/lang/{fr,en,ar,tr}/`
- **Garde CI (PA2-I18N-007 + issue #5432)** : `check-hardcoded-accented-messages.sh` refuse toute ligne AJOUTÉE avec un littéral accentué (proxy « texte français ») hors `__()`/`trans()` sur les surfaces à risque :
  - `*Controller.php` (historique) — `api/app/Modules/*/Application/**` (Services/Actions) — `api/app/Modules/*/Domain/Exceptions/**` — `api/app/Modules/*/Console/**`
  - Les lignes `__('catalogue.cle')` et les codes techniques sans accent ne déclenchent jamais.

## 3. Tests

### 3.1 Backend (Pest/PHPUnit)

- **Minimum 1 test Feature par endpoint API**
- Utiliser `RefreshDatabase` trait
- Factories pour generer les donnees de test
- Tester : CRUD, workflow (status transitions), RBAC (roles autorises/refuses), isolation tenant
- Repertoire : `tests/Feature/` et `tests/Unit/`
- Commande : `php artisan test`
- **Coverage gate** : seuil bloquant 65 % (mesuré 71,11 % au 17/08/2026, ratchet depuis 60 % le 10/08)

#### Règle PendingCommand — `run()` explicite (#1596)

`$this->artisan(...)` retourne un `PendingCommand` : la commande s'exécute
**paresseusement dans `__destruct()`**, pas immédiatement.
Toute assertion sur l'état de la base (ou tout effet secondaire) qui suit
l'assignation sans appel explicite tournera **avant** que la commande soit
réellement exécutée.

**Toujours appeler `->run()` avant la première assertion DB :**

```php
// ❌ Mauvais — la commande s'exécute après les assertions (ordre trompeur)
$cmd = $this->artisan('my:command');
$this->assertDatabaseHas('table', [...]);  // évalué AVANT la commande

// ✅ Correct
$cmd = $this->artisan('my:command');
$cmd->run();  // exécution synchrone immédiate
$this->assertDatabaseHas('table', [...]);

// ✅ Aussi correct (enchaînement sans assignation)
$this->artisan('my:command')->assertSuccessful();
```

6 fichiers de test à risque identifiés lors de l'audit 2026-08-09
(`grep -r '= \$this->artisan(' tests/`) ; corriger au fur et à mesure.

### 3.2 Frontend E2E (Playwright)

- Specs dans `front/admin-dashboard/e2e/`
- Config : `front/admin-dashboard/playwright.config.js`
- Tester : navigation, login, workflows critiques (paie, conges, recrutement)

### 3.3 Mobile (Flutter)

- Tests widget + unit dans `front/mobile_apps/<app>/test/` (voir `front/mobile_apps/README.md`)
- Commande : `flutter test`
- Framework state : flutter_riverpod 3.3 (pas Bloc)
- **Coverage gate** : seuil actuel 21%, cible 25%

## 4. Git & CI

### 4.1 Branches

- **`main`** — branche protegee, source de verite
- **Feature** : `devin/<timestamp>-description` ou `feature/description`
- **Fix** : `fix/description`
- **Ne jamais push directement sur `main`** — toujours via PR

### 4.2 Commits

- Format : `type: description courte`
- Types : `feat`, `fix`, `docs`, `test`, `ci`, `refactor`, `perf`, `chore`
- Exemples : `feat: add SEPA export for payroll`, `fix: tenant isolation in reports`

### 4.3 PR

- **CHANGELOG.md obligatoire** pour tout changement de comportement
- **Taille du CHANGELOG** : `CHANGELOG.md` doit rester lisible (< 150 Ko).
  À chaque release : archiver dans `CHANGELOG_ARCHIVE.md` les sections sorties du
  périmètre (6 mois + release courante condensée si > 50 Ko + `[Unreleased]`),
  et nettoyer `docs/archive/` des fichiers non référencés (l'historique git
  conserve tout) — issue #1729.
- **CI doit etre vert** — GitHub Actions est la source de verite (pas la validation locale)
- Workflows critiques : `tests.yml`, `coverage-gate.yml`, `backend-jobs-ci.yml`, `architecture-check.yml` (phpstan-modules + phpstan-strict), `mobile-apps-ci.yml`, `governance`, `CodeQL`, `secret-scan`

### 4.4 Fichiers interdits en commit

- `.env`, `credentials.json`, tout fichier contenant des secrets
- Fichiers generes : `node_modules/`, `vendor/`, `storage/`, `bootstrap/cache/`

## 5. Frontend (Vue.js / Admin Dashboard)

- **Vue 3 + Composition API** — pas Options API
- **Vite** comme bundler
- Code splitting deja actif
- Composants dans `front/admin-dashboard/src/components/`
- i18n : utiliser le systeme de traduction centralise

## 6. Mobile (Flutter)

- **Flutter 3.x** — versions stables uniquement
- **State management** : `flutter_riverpod 3.3` (pas Bloc, pas Provider legacy)
- **Navigation** : GoRouter
- Repertoire : `front/mobile_apps/<app>/lib/` (voir `front/mobile_apps/README.md` pour la liste des apps)

## 7. Documentation API

- **OpenAPI/Swagger** — fichier canonique : `api/openapi.yaml`
- Accessible sur `/docs` en dev
- **Tout nouvel endpoint DOIT etre documente** dans openapi.yaml
- Format : OpenAPI 3.0+

## 8. Securite

- Audits documentes dans `docs/security/` :
  - `RBAC_ROUTE_MATRIX.md` — matrice routes/roles
  - `SQL_INJECTION_AUDIT.md` — audit SQLi
  - `ADMIN_CSRF_XSS_AUDIT.md` — audit CSRF/XSS
- **Rate limiting** par plan d'abonnement
- **OWASP ZAP** en CI avec flag `-I` (informational, non-bloquant)
- **Secret scanning** via TruffleHog en CI

## 9. Pieges connus

| Piege | Solution |
|-------|---------|
| `App\AI\AIOrchestrator` | N'existe pas — utiliser `App\AI\Orchestrator` |
| Analytics IA | Reserve aux `principal_managers` + RH |
| `TenantMiddleware` | Garder `try/finally` autour de `resetToPrevious()` |
| PHPStan baseline | Diff-gate, ne jamais elargir le fichier neon |
| `throttle:api` dans un groupe interne | Double consommation du compteur (groupe api par défaut + explicite) — retirer après vérification `route:list` (#4932) |
| SEPA export | FR/MA uniquement ; CPA/BNA pour DZ ; CSV standard pour les autres |
| Admin views | Consomment les vrais endpoints backend (pas de mock) |

## 10. References

- [AGENTS.md](./AGENTS.md) — Guide operationnel complet
- [docs/archive/PLAN_ACTION/](./docs/archive/PLAN_ACTION/) — Plans d'action detailles
- [docs/validation/](./docs/validation/) — Rapports de readiness
- [docs/security/](./docs/security/) — Audits securite


## Verbes HTTP — actions métier (issue #4930)

Convention unique, appliquée à toutes les routes API :

- **POST** — créer une ressource ET déclencher une action métier
  (approve/reject/disburse/validate/complete/skip/activate…).
- **PUT/PATCH** — modifier une ressource existante (mise à jour d'état
  de la ressource elle-même).
- **GET** — lecture pure, sans effet de bord (exceptions documentées :
  magic links email, exports idempotents).

Rétrocompatibilité : les anciens verbes restent acceptés comme **alias
dépréciés** le temps de la migration des clients Flutter — listés dans
`dev-hub/tools/openapi-coverage-allowlist.txt` (#4930).
