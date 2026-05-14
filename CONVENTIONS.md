# Conventions de Code — Leopardo RH

> Ce document definit les standards de code obligatoires pour tout contributeur (humain ou IA).
> Derniere mise a jour : 2026-05-14

---

## 1. Structure du projet

```
leopardo-hr/
├── api/                    # Backend Laravel (PHP 8.2+)
├── front/
│   ├── admin-dashboard/    # Dashboard admin (Vue.js / Vite)
│   └── web/                # Vitrine (Next.js)
├── mobile/                 # Application Flutter (Dart)
├── docs/                   # Documentation technique
├── .github/workflows/      # CI/CD GitHub Actions
├── docker-compose.yml      # Dev environment
└── AGENTS.md               # Guide operationnel agents IA
```

## 2. PHP / Laravel (Backend)

### 2.1 Style general

- **PHP 8.2 minimum** — utiliser les fonctionnalites modernes (enums, readonly, named args, match)
- **`declare(strict_types=1);`** en haut de chaque fichier PHP (sauf config)
- **Namespace PSR-4** — `App\Http\Controllers\Api\V1\*`, `App\Models\*`, `App\Services\*`
- **PHPStan level max** — tout code doit passer `phpstan analyse` sans erreur au-dela du baseline
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
├── Http/
│   ├── Controllers/Api/V1/     # Controllers API versionnés
│   ├── Requests/Api/           # FormRequest validation
│   └── Resources/Api/          # API Resources (transformation JSON)
├── Models/                     # Eloquent models + traits
├── Services/                   # Business logic
├── Policies/                   # Authorization RBAC
├── Events/                     # Domain events
├── Listeners/                  # Event handlers
├── Traits/                     # BelongsToCompany, Approvable, etc.
└── AI/                         # Couche IA (Orchestrator, Providers, Tools)
```

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

### 2.7 DDD pour nouveaux modules

Les **nouveaux modules** suivent la structure DDD (template dans `stubs/module-template/`) :

```
Domain/         # Entites, Value Objects, Repository Interfaces, Domain Events
Application/    # Use Cases, DTOs, Command/Query handlers
Infrastructure/ # Implementations Repository, Integrations externes
Interfaces/     # Controllers, Resources, Requests
```

Les modules existants (pre-sprint) gardent la structure Laravel classique.

### 2.8 i18n

- **Utiliser `__()` ou `trans()`** — jamais de chaines hardcodees en francais/anglais
- 4 langues supportees : FR, EN, AR, TR
- Support RTL pour l'arabe
- Fichiers de traduction dans `resources/lang/{fr,en,ar,tr}/`

## 3. Tests

### 3.1 Backend (Pest/PHPUnit)

- **Minimum 1 test Feature par endpoint API**
- Utiliser `RefreshDatabase` trait
- Factories pour generer les donnees de test
- Tester : CRUD, workflow (status transitions), RBAC (roles autorises/refuses), isolation tenant
- Repertoire : `tests/Feature/` et `tests/Unit/`
- Commande : `php artisan test`
- **Coverage gate** : seuil actuel 55%, cible 60%

### 3.2 Frontend E2E (Playwright)

- Specs dans `front/admin-dashboard/e2e/`
- Config : `front/admin-dashboard/playwright.config.js`
- Tester : navigation, login, workflows critiques (paie, conges, recrutement)

### 3.3 Mobile (Flutter)

- Tests widget + unit dans `mobile/test/`
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
- **CI doit etre vert** — GitHub Actions est la source de verite (pas la validation locale)
- Workflows critiques : `backend`, `backend quality`, `mobile`, `build`, `lint`, `type-check`, `test Node 20`, `CodeQL`, `governance`

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
- Repertoire : `mobile/lib/`

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
| SEPA export | FR/MA uniquement ; CPA/BNA pour DZ ; CSV standard pour les autres |
| Admin views | Consomment les vrais endpoints backend (pas de mock) |

## 10. References

- [AGENTS.md](./AGENTS.md) — Guide operationnel complet
- [docs/PLAN_ACTION/](./docs/PLAN_ACTION/) — Plans d'action detailles
- [docs/validation/](./docs/validation/) — Rapports de readiness
- [docs/security/](./docs/security/) — Audits securite
