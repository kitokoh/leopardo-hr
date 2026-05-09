# Regression Test Suite

To ensure the stability of Leopardo RH, we maintain a registry of critical regression tests that must pass before any release.

## 🔑 Critical Paths

### 1. Multi-Tenant Isolation
- **Scenario:** Authenticate as Employee A (Company 1) and attempt to access Employee B (Company 2).
- **Expected:** API returns `404 Not Found` (Obscurity over 403).
- **Test File:** `api/tests/Feature/Security/TenantIsolationTest.php`

### 2. Payroll Calculations
- **Scenario:** Calculate payroll for an employee with 2 hours of overtime and 1 day of unpaid absence.
- **Expected:** Net salary matches the business rule formula exactly.
- **Test File:** `api/tests/Feature/Payroll/CalculationTest.php`

### 3. Biometric Synchronization
- **Scenario:** Enroll a fingerprint on a ZKTeco device and sync with the backend.
- **Expected:** `zkteco_id` is correctly mapped and logs appear in `attendance_logs`.

## 🚀 Execution Registry

| Module | Level | CI Job | Frequency |
|--------|-------|--------|-----------|
| **Auth** | Critical | `Tests - Backend` | Every PR |
| **Attendance** | High | `Tests - Backend` | Every PR |
| **RBAC** | Critical | `Tests - Backend` | Every PR |
| **I18N** | Medium | `Tests - I18N` | Weekly / PR |

---

For the full list of scenarios, see [REGISTRE_SCENARIOS_TESTS.md](../../docs/GESTION_PROJET/REGISTRE_SCENARIOS_TESTS.md).
