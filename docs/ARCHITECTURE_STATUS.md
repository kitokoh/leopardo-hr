# Architecture DDD — État des modules

> Mis à jour le 2026-06-30 | PR #812 mergé + Phase 2 DDD layers en cours

## 1. Tableau de l'état DDD par module

Légende :
- ✅ Couche présente et structurée
- ⚠️ Couche partielle (dossier présent mais couches internes manquantes)
- ❌ Couche absente

| Module          | Domain | Domain/Contracts | Domain/Exceptions | Application | Application/DTOs | Infrastructure | Interfaces | Providers | Tests |
|-----------------|:------:|:----------------:|:-----------------:|:-----------:|:----------------:|:--------------:|:----------:|:---------:|:-----:|
| **Absence**     | ✅     | ✅               | ✅                | ✅          | ✅               | ✅             | ✅         | ✅        | ✅    |
| **Attendance**  | ✅     | ✅               | ✅                | ✅          | ✅               | ✅             | ✅         | ✅        | ✅    |
| **Billing**     | ✅     | ✅               | ✅                | ✅          | ✅               | ✅             | ✅         | ✅        | ✅    |
| **Cabinet**     | ✅     | ✅               | ✅                | ✅          | ✅               | ✅             | ✅         | ✅        | ✅    |
| **Cameras**     | ✅     | ✅               | ✅                | ✅          | ✅               | ✅             | ✅         | ✅        | ✅    |
| **Expense**     | ✅     | ✅               | ✅                | ✅          | ✅               | ✅             | ✅         | ✅        | ✅    |
| **Fleet**       | ✅     | ✅               | ✅                | ✅          | ✅               | ✅             | ✅         | ✅        | ✅    |
| **HR**          | ✅     | ✅               | ✅                | ✅          | ✅               | ✅             | ✅         | ✅        | ✅    |
| **Notification**| ✅     | ✅               | ✅                | ✅          | ✅               | ✅             | ✅         | ✅        | ✅    |
| **Payroll**     | ✅     | ✅               | ✅                | ✅          | ✅               | ✅             | ✅         | ✅        | ✅    |
| **Planning**    | ✅     | ✅               | ✅                | ✅          | ✅               | ✅             | ✅         | ✅        | ✅    |
| **Recruitment** | ✅     | ✅               | ✅                | ✅          | ✅               | ✅             | ✅         | ✅        | ✅    |

> **12/12 modules 100% complets** après PR Phase 2 DDD layers.

### Notes sur les nouveaux modules (PRs open)

- **SmartAttendance** (PR #811) — Nouveau module GPS auto check-in. Structure DDD complète + 5 suites de tests.
- **EdgeSync** (PR #813) — Module Edge Sync offline-first. Structure DDD + tests Feature + Docker compose.

---

## 2. Ce qui a été ajouté en Phase 2 (cette PR)

### Domain/Contracts ajoutés (10 modules)
| Module | Contrats |
|--------|---------|
| Absence | `AbsenceRepositoryInterface` |
| Attendance | `AttendanceRepositoryInterface` |
| Billing | `SubscriptionRepositoryInterface`, `InvoiceRepositoryInterface` |
| Cabinet | `DocumentRepositoryInterface`, `FolderRepositoryInterface` |
| Cameras | `CameraRepositoryInterface`, `AccessTokenServiceInterface` |
| Expense | `ExpenseRepositoryInterface` |
| Fleet | `VehicleRepositoryInterface`, `TripRepositoryInterface` |
| Notification | `NotificationRepositoryInterface` |
| Planning | `PlanningRepositoryInterface` |
| Recruitment | `JobPostingRepositoryInterface` |

### Domain/Exceptions ajoutés (5 modules)
| Module | Exceptions |
|--------|-----------|
| Billing | `ExpiredSubscriptionException`, `SubscriptionAlreadyActiveException`, `InvalidSubscriptionPlanException` |
| Cabinet | `DocumentNotFoundException`, `DocumentAccessDeniedException` |
| Cameras | `CameraNotFoundException`, `InvalidAccessTokenException`, `CameraAccessDeniedException` |
| Fleet | `VehicleNotFoundException`, `VehicleAlreadyAssignedException`, `MaintenanceRequiredException` |
| Notification | `NotificationNotFoundException`, `NotificationDeliveryException` |

### Application/DTOs ajoutés (6 modules)
| Module | DTOs |
|--------|------|
| Billing | `CreateSubscriptionDTO`, `InvoiceDTO` |
| Cabinet | `UploadDocumentDTO` |
| Fleet | `AssignVehicleDTO`, `LogTripDTO` |
| Notification | `SendNotificationDTO` |
| Planning | `CreateShiftDTO` |
| Recruitment | `CreateJobPostingDTO` |

### Autres
- `Cameras/Providers/CamerasServiceProvider.php` — Provider Cameras créé (déjà référencé dans `bootstrap/providers.php`)
- `tests/Feature/HR/HrControllerTest.php` — Tests HR cross-tenant + RBAC
- `tests/Feature/Cabinet/CabinetDocumentControllerTest.php` — Tests Cabinet upload + cross-tenant isolation

---

## 3. Controllers encore dans `App\Http\Controllers\Api\V1` à migrer

**Remarque :** Plusieurs controllers ont une double existence (legacy namespace + module DDD). La migration complète est Phase 3.

### Migrés (controllers DDD présents dans les modules)
```
AbsenceController       → App\Modules\Absence\Interfaces\Api\V1\Controllers\
ExpenseClaimController  → App\Modules\Expense\Interfaces\Api\V1\Controllers\
AttendanceController    → App\Modules\Attendance\Interfaces\Api\V1\Controllers\
RecruitmentController   → App\Modules\Recruitment\Interfaces\Api\V1\Controllers\
```

### Encore à migrer (Phase 3 — par vagues)

**Vague A — HR core (~20 controllers) :**
```
EmployeeController, DepartmentController, ContractController, EvaluationController,
InvitationController, OrgChartController, TrainingController, RoleAssignmentController,
SelfServiceController, PositionController, HrController, HrReportController,
EmployeeImportController, OnboardingController, OnboardingChecklistController,
OnboardingQrController, OnboardingStepController
```

**Vague B — Finance/Payroll (~15 controllers) :**
```
PayrollController, PayrollCycleController, PayrollRunController, PaySlipController,
SalaryAdvanceController, SalaryComponentController, SalaryStructureController,
BankExportController, BulkPaymentController, CotisationSimulationController,
SocialContributionController, SocialDeclarationController, TaxSlabController
```

**Vague C — Modules spécialisés (~20 controllers) :**
```
FleetController, VehicleController, VehicleTripController, VehicleMaintenanceController,
VehicleAlertController, BillingController, CabinetDocumentController,
CabinetFolderController, CabinetShareController, NotificationController,
PlanningController, ProjectController, ScheduleController, TaskController
```

**Vague D — Platform (~12 controllers) :**
```
PlatformCompanyFeatureController, PlatformCompanyHealthController,
PlatformCompanyRequestController, PlatformCompanySubscriptionController,
PlatformMetricsOverviewController, PlatformPlanController,
PlatformCrmPipelineController, PlatformCountryDefaultsController
```

---

## 4. Roadmap Phase 3–4

| Phase | Item | Description | Effort |
|-------|------|-------------|--------|
| P3.1 | **Migration controllers HR** | Vague A (~20 controllers) | Élevé |
| P3.2 | **Migration controllers Payroll** | Vague B (~15 controllers) | Élevé |
| P3.3 | **Module Training standalone** | Extraire de HR → `App\Modules\Training\` | Moyen |
| P3.4 | **Module Onboarding** | Consolider 4 Onboarding* controllers | Moyen |
| P4.1 | **PHPStan level 5+** | Progressif via `phpstan-baseline.neon` | Moyen |
| P4.2 | **Module Platform** | Consolider 12 Platform* controllers | Moyen |
| P4.3 | **OpenAPI/Swagger** | `composer require dedoc/scramble` | Faible |
| P4.4 | **Event Sourcing** | Absence + Expense → CQRS + Event Store | Très élevé |
| P4.5 | **Row-Level Security** | PostgreSQL RLS remplace filtres `company_id` | Très élevé |
| P4.6 | **i18n backend** | Messages d'erreur `fr/en/ar` via `lang/` | Moyen |

---

## 5. Récapitulatif

| Indicateur | Avant Phase 2 | Après Phase 2 |
|------------|:-------------:|:-------------:|
| Modules 100% complets | 1/12 (Payroll) | **12/12** |
| Modules avec `Domain/Contracts` | 2/12 | **12/12** |
| Modules avec `Domain/Exceptions` | 7/12 | **12/12** |
| Modules avec `Application/DTOs` | 6/12 | **12/12** |
| Modules avec `Providers` | 11/12 | **12/12** |
| Modules avec tests Feature | 10/12 | **12/12** |
| Controllers legacy à migrer | ~90 | ~85 (5 migrés) |
| Coverage gate | 65% | 65% (unchanged) |
