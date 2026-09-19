# Testing Strategy & Guidelines

Leopardo maintains high reliability through a comprehensive multi-layered testing strategy.

## 🧪 Testing Pyramid

| Layer | Tool | Coverage Goal | Responsibility |
|-------|------|---------------|----------------|
| **Unit / Feature** | Pest PHP (`api/`) | Backend ≥ 65 % (gate CI), 80 %+ sur la logique métier | API Contracts, RBAC, calculs |
| **Widget / Unit** | Flutter (`front/mobile_apps/*`, via Melos) | Écrans et logique core | Mobile Experience |
| **Unit** | Jest (`front/web`), Vitest (`front/web-offline`) | Logique vitrine/PWA | Contenus & offline |
| **E2E** | Playwright (`front/web/e2e`, `front/admin-dashboard/e2e`) | Parcours critiques (funnel, admin) | Browser Integration |
| **Unit** | JS/Python (`front/zkteco-kiosk`, `edge`) | Kiosk & edge-sync | Surfaces terrain |

Pyramide alignée sur les surfaces réelles du monorepo (backend Laravel, vitrine
Next.js, admin Vue, apps Flutter, kiosk ZKTeco, edge) — voir le registre canonique
des scénarios : [REGISTRE_SCENARIOS_TESTS.md](../GESTION_PROJET/REGISTRE_SCENARIOS_TESTS.md).

## 🚀 Running Tests

### 1. Backend (API)
We use Pest for a modern testing experience.

```bash
cd api
# Run with SQLite in-memory for speed
DB_CONNECTION=sqlite DB_DATABASE=:memory: ./vendor/bin/pest
```

### 2. Frontend (Web & Admin)
```bash
# Vitrine Next.js
cd front/web
npm run lint
npx jest  # suite vitrine (633 tests) — à exécuter en local en attendant le job CI

# Admin dashboard (Vue)
cd front/admin-dashboard
npm run lint
npx playwright test  # E2E admin
```

### 3. Mobile (Flutter — monorepo Melos)

Les apps vivent dans `front/mobile_apps/` (packages Melos, `melos.yaml` à la
racine). Depuis la racine du dépôt :

```bash
melos bootstrap       # une fois, après clone (prépare les liens entre packages)
melos run analyze     # dart analyze sur tous les packages
melos run test        # unit + widget tests (tous les packages avec un dossier test/)
melos run test:coverage
```

Ou dans une app précise :

```bash
cd front/mobile_apps/leopardo_employee
flutter test
flutter analyze
```

> Les tests d'intégration sur device/émulateur (`integration_test/`) n'existent
> pas encore dans les apps (constat 2026-09-09, protocole P01) — à créer pour
> couvrir les parcours critiques mobile.

## 🛡️ Critical Scenarios

All contributions must pass the following critical scenarios:
- **Tenant Isolation:** Ensure Company A cannot access Company B's data.
- **RBAC Enforcement:** Ensure Employees cannot access Manager-only endpoints.
- **Data Integrity:** Verify payroll and attendance calculations.

Detailed test registry: [REGISTRE_SCENARIOS_TESTS.md](../GESTION_PROJET/REGISTRE_SCENARIOS_TESTS.md).

## 📊 Continuous Integration

Our GitHub Actions workflows execute the suites par surface (`.github/workflows/` :
`tests.yml` backend, `mobile-apps-ci.yml` Flutter, `web-marketing-ci.yml` vitrine,
`e2e-isolated.yml` Playwright…). Voir la cartographie : `.github/workflows/README.md`.

---

For local Docker-based testing, refer to [RUNBOOK_LOCAL_TESTS.md](../GESTION_PROJET/RUNBOOK_LOCAL_TESTS.md).
