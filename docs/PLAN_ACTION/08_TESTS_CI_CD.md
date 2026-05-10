# 08 — TESTS & CI/CD

**Objectif :** Strategie de tests complete et pipelines CI/CD robustes pour un deploiement automatique fiable.

---

## 1. Strategie de tests

### Pyramide de tests

```
          /  E2E (Playwright)  \        ← 10% — parcours critiques
         / Integration (Feature) \      ← 60% — endpoints API complets
        /    Unit (Pest PHP)      \     ← 30% — logique metier isolee
```

### Backend (Pest PHP)

#### Tests Unit

Tester la logique metier isolee, sans DB ni HTTP :

```
tests/Unit/
    Payroll/
        PayrollCalculatorTest.php        # Calculs paie par pays
        AlgeriaPayrollRulesTest.php      # Regles DZ specifiques
        MoroccoPayrollRulesTest.php
        TunisiaPayrollRulesTest.php
        FrancePayrollRulesTest.php
        TurkeyPayrollRulesTest.php
        SenegalPayrollRulesTest.php
    AI/
        IntentEngineTest.php             # Detection d'intention
        ToolRegistryTest.php             # Filtrage outils par role
    Tracking/
        TraccarServiceTest.php           # Mock API Traccar
    Leave/
        AccrualCalculatorTest.php        # Calcul accumulation conges
        CarryForwardTest.php             # Report conges
```

#### Tests Feature (Integration)

Tester les endpoints API complets avec DB (SQLite en memoire ou PostgreSQL) :

```
tests/Feature/
    # Modules existants (deja partiellement couverts)
    Auth/
    Employee/
    Attendance/
    Absence/

    # Nouveaux modules
    Payroll/
        PayrollRunTest.php               # CRUD + calculate + validate
        PaySlipTest.php                  # Generation bulletins
        SalaryStructureTest.php          # CRUD structures
        BankExportTest.php               # Generation export
    Leave/
        LeavePolicyTest.php              # CRUD politiques
        LeaveBalanceTest.php             # Soldes et recalcul
        LeaveApprovalWorkflowTest.php    # Multi-niveaux
    Contract/
        ContractTest.php                 # CRUD + workflows
        ContractAlertTest.php            # Alertes expiration
    Recruitment/
        JobPostingTest.php               # CRUD + publish/close
        ApplicantTest.php                # CRUD + pipeline
        InterviewTest.php                # CRUD + feedback
    Training/
        TrainingCourseTest.php
        TrainingSessionTest.php
        TrainingEnrollmentTest.php
    Loan/
        EmployeeLoanTest.php             # CRUD + workflow
        LoanRepaymentTest.php            # Echeancier
    Expense/
        ExpenseClaimTest.php             # CRUD + workflow
    Report/
        ReportTest.php                   # Chaque rapport
    Webhook/
        WebhookEndpointTest.php          # CRUD
        WebhookDeliveryTest.php          # Envoi + signature
    Audit/
        AuditLogTest.php                 # Log automatique
    AI/
        AIChatTest.php                   # Endpoint chat
        AIQuotaTest.php                  # Rate limiting
    Tracking/
        VehicleTest.php                  # CRUD
        VehicleTripTest.php
        VehicleAlertTest.php
    OrgChart/
        OrgChartTest.php
```

#### Convention de test

```php
// Chaque test Feature suit ce pattern :
it('creates a payroll run', function () {
    $manager = Employee::factory()->manager()->create();
    actingAs($manager->user)
        ->postJson('/api/v1/payroll-runs', [
            'period_start' => '2026-05-01',
            'period_end' => '2026-05-31',
        ])
        ->assertCreated()
        ->assertJsonStructure(['data' => ['id', 'status', 'period_start']]);
});

it('forbids employee from creating payroll run', function () {
    $employee = Employee::factory()->create();
    actingAs($employee->user)
        ->postJson('/api/v1/payroll-runs', [...])
        ->assertForbidden();
});
```

### Frontend (Playwright E2E)

#### Parcours critiques a tester

```
tests/e2e/
    auth.spec.ts                    # Login, logout, 2FA
    employee-crud.spec.ts           # Creer, modifier, archiver employe
    attendance-flow.spec.ts         # Check-in, check-out, historique
    payroll-run.spec.ts             # Creer run, calculer, valider, PDF
    leave-request.spec.ts           # Demander, approuver, verifier solde
    recruitment-pipeline.spec.ts    # Publier offre, candidater, pipeline
    admin-dashboard.spec.ts         # Cockpit, entreprises, abonnements
    blog.spec.ts                    # Liste articles, article individuel
    ai-chat.spec.ts                 # Envoyer message, recevoir reponse
```

### Mobile (Flutter Test)

```
test/
    unit/
        models/                     # Serialization/deserialization
        blocs/                      # State management logic
    widget/
        screens/                    # Widget rendering
    integration/
        login_flow_test.dart        # Login complet
        attendance_flow_test.dart   # Pointage complet
```

---

## 2. Coverage

### Objectif

| Surface | Coverage cible | Actuel estime |
|---------|---------------|---------------|
| Backend API | 80% | ~40% |
| Admin Dashboard | 60% | ~10% |
| Mobile Flutter | 50% | ~15% |

### Configuration PHPUnit coverage

```xml
<!-- api/phpunit.xml — ajouter -->
<coverage>
    <report>
        <clover outputFile="coverage/clover.xml"/>
        <html outputDirectory="coverage/html"/>
    </report>
    <include>
        <directory suffix=".php">app</directory>
    </include>
    <exclude>
        <directory>app/Console</directory>
        <directory>app/Providers</directory>
    </exclude>
</coverage>
```

### Seuil progressif

```yaml
# Dans le workflow CI
- name: Check coverage
  run: |
    COVERAGE=$(php -r "echo json_decode(file_get_contents('coverage/clover.xml'))->project->metrics['coveredconditionals'] / ...")
    if (( $(echo "$COVERAGE < 60" | bc -l) )); then
      echo "Coverage too low: $COVERAGE%"
      exit 1
    fi
```

Commencer a 40%, monter de 5% par mois.

---

## 3. CI/CD Pipelines

### Workflows existants

| Workflow | Fichier | Declencheur |
|----------|---------|-------------|
| Tests backend | `tests.yml` | Push/PR sur api/** |
| PHPStan | `phpstan-baseline.yml` | Push/PR sur api/** |
| CodeQL | `codeql.yml` | Push/PR |
| Secret scan | `secret-scan.yml` | Push/PR |
| Web CI | `web-ci.yml` | Push/PR sur admin-dashboard/** |
| Web marketing | `web-marketing-ci.yml` | Push/PR sur web/** |
| Mobile | `mobile-distribute.yml` | Push/PR sur mobile/** |
| Deploy | `deploy-main.yml` | Push sur main |

### Workflows a creer/ameliorer

#### 1. Backend Quality (ameliore)

```yaml
# .github/workflows/backend-quality.yml
name: Backend Quality
on:
  push:
    paths: ['api/**']
  pull_request:
    paths: ['api/**']

jobs:
  lint:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with: { php-version: '8.4' }
      - run: composer install --no-interaction --prefer-dist
        working-directory: api
      - run: vendor/bin/pint --test
        working-directory: api

  phpstan:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with: { php-version: '8.4' }
      - run: composer install --no-interaction --prefer-dist
        working-directory: api
      - run: vendor/bin/phpstan analyse --level=6 --error-format=github
        working-directory: api

  tests:
    runs-on: ubuntu-latest
    services:
      postgres:
        image: postgres:16
        env:
          POSTGRES_DB: leopardo_test
          POSTGRES_USER: postgres
          POSTGRES_PASSWORD: secret
        ports: ['5432:5432']
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with: { php-version: '8.4', coverage: pcov }
      - run: composer install --no-interaction --prefer-dist
        working-directory: api
      - run: vendor/bin/pest --coverage --min=40
        working-directory: api
        env:
          DB_CONNECTION: pgsql
          DB_HOST: localhost
          DB_DATABASE: leopardo_test
          DB_USERNAME: postgres
          DB_PASSWORD: secret
      - uses: actions/upload-artifact@v4
        with:
          name: coverage-report
          path: api/coverage/
```

#### 2. E2E Playwright

```yaml
# .github/workflows/e2e.yml
name: E2E Tests
on:
  pull_request:
    paths: ['admin-dashboard/**', 'api/**']

jobs:
  e2e:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with: { php-version: '8.4' }
      - uses: actions/setup-node@v4
        with: { node-version: '20' }
      - name: Setup API
        run: |
          cd api && composer install
          cp .env.example .env && php artisan key:generate
          php artisan migrate --seed
          php artisan serve &
      - name: Setup Dashboard
        run: |
          cd admin-dashboard && npm ci && npm run build
          npx serve -s out -p 3000 &
      - name: Run Playwright
        run: |
          cd admin-dashboard
          npx playwright install --with-deps
          npx playwright test
      - uses: actions/upload-artifact@v4
        if: failure()
        with:
          name: playwright-report
          path: admin-dashboard/playwright-report/
```

#### 3. Mobile CI

```yaml
# .github/workflows/mobile-ci.yml
name: Mobile CI
on:
  push:
    paths: ['mobile/**']
  pull_request:
    paths: ['mobile/**']

jobs:
  analyze-test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: subosito/flutter-action@v2
        with: { channel: stable }
      - run: flutter pub get
        working-directory: mobile
      - run: flutter analyze
        working-directory: mobile
      - run: flutter test --coverage
        working-directory: mobile
```

#### 4. Deploy staging automatique

```yaml
# .github/workflows/deploy-staging.yml
name: Deploy Staging
on:
  push:
    branches: [develop]

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - name: Deploy to Render Staging
        env:
          RENDER_API_KEY: ${{ secrets.RENDER_API_KEY }}
        run: |
          curl -X POST "https://api.render.com/v1/services/$STAGING_SERVICE_ID/deploys" \
            -H "Authorization: Bearer $RENDER_API_KEY"
```

---

## 4. Pre-commit hooks

### Configuration

```yaml
# .pre-commit-config.yaml (a la racine)
repos:
  - repo: local
    hooks:
      - id: php-lint
        name: PHP Pint
        entry: bash -c 'cd api && vendor/bin/pint'
        language: system
        files: '\.php$'
        pass_filenames: false

      - id: php-stan
        name: PHPStan
        entry: bash -c 'cd api && vendor/bin/phpstan analyse --no-progress'
        language: system
        files: '\.php$'
        pass_filenames: false

      - id: no-secrets
        name: Check for secrets
        entry: bash -c 'git diff --cached --name-only | xargs grep -l "PRIVATE_KEY\|password\s*=\s*\"[^\"]\+\"" || true'
        language: system
```

---

## 5. Taches

- [ ] **T-CI-01** : Ameliorer `tests.yml` avec coverage et PostgreSQL
- [ ] **T-CI-02** : Creer le workflow E2E Playwright
- [ ] **T-CI-03** : Ameliorer le workflow mobile (analyze + test + coverage)
- [ ] **T-CI-04** : Creer le workflow deploy staging
- [ ] **T-CI-05** : Configurer les seuils de coverage progressifs
- [ ] **T-CI-06** : Ajouter `.pre-commit-config.yaml`
- [ ] **T-CI-07** : Ajouter les badges CI/coverage au README.md
- [ ] **T-CI-08** : Configurer les branch protection rules sur GitHub (require CI pass)
- [ ] **T-CI-09** : Creer les factories manquantes pour les nouveaux modeles
- [ ] **T-CI-10** : Ecrire les tests Feature pour tous les nouveaux modules (voir Section 1)
- [ ] **T-CI-11** : Configurer Playwright pour admin-dashboard
- [ ] **T-CI-12** : Ajouter un job de security audit (composer audit, npm audit)
