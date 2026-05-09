# Testing Strategy & Guidelines

Leopardo RH maintains high reliability through a comprehensive multi-layered testing strategy.

## 🧪 Testing Pyramid

| Layer | Tool | Coverage Goal | Responsibility |
|-------|------|---------------|----------------|
| **Unit** | Pest PHP | 80%+ | Logic & Calculations |
| **Feature** | Pest PHP | 100% Endpoints | API Contracts & RBAC |
| **E2E** | Playwright | Critical Flows | Browser Integration |
| **Mobile** | Flutter Test | Core Screens | Mobile Experience |

## 🚀 Running Tests

### 1. Backend (API)
We use Pest for a modern testing experience.

```bash
cd api
# Run with SQLite in-memory for speed
DB_CONNECTION=sqlite DB_DATABASE=:memory: ./vendor/bin/pest
```

### 2. Frontend (Web)
```bash
cd web
npm run lint
npm run test  # if applicable
```

### 3. Mobile
```bash
cd mobile
flutter test
flutter analyze
```

## 🛡️ Critical Scenarios

All contributions must pass the following critical scenarios:
- **Tenant Isolation:** Ensure Company A cannot access Company B's data.
- **RBAC Enforcement:** Ensure Employees cannot access Manager-only endpoints.
- **Data Integrity:** Verify payroll and attendance calculations.

Detailed test registry: [REGISTRE_SCENARIOS_TESTS.md](docs/GESTION_PROJET/REGISTRE_SCENARIOS_TESTS.md).

## 📊 Continuous Integration

Our GitHub Actions workflows (`.github/workflows/tests.yml`) execute the full suite on every Pull Request.
- **Gate 1:** Linting & Static Analysis (PHPStan, ESLint).
- **Gate 2:** Functional Tests.
- **Gate 3:** Build Verification.

---

For local Docker-based testing, refer to [RUNBOOK_LOCAL_TESTS.md](docs/GESTION_PROJET/RUNBOOK_LOCAL_TESTS.md).
