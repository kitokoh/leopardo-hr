# Architecture DDD — État des modules

> Généré le 2026-06-29 | Branche : `feat/ddd-audit-and-loan-tests`

## 1. Tableau de l'état DDD par module

Légende :
- ✅ Couche présente et structurée
- ⚠️ Couche partielle (dossier présent mais couches internes manquantes)
- ❌ Couche absente

| Module        | Domain | Domain/Contracts | Domain/Exceptions | Application | Application/DTOs | Infrastructure | Interfaces | Providers | Tests |
|---------------|:------:|:----------------:|:-----------------:|:-----------:|:----------------:|:--------------:|:----------:|:---------:|:-----:|
| **Absence**   | ✅     | ❌               | ✅                | ✅          | ✅               | ✅             | ✅         | ✅        | ✅    |
| **Attendance**| ✅     | ❌               | ✅                | ✅          | ✅               | ✅             | ✅         | ✅        | ✅    |
| **Billing**   | ✅     | ❌               | ❌                | ✅          | ❌               | ✅             | ✅         | ✅        | ✅    |
| **Cabinet**   | ✅     | ❌               | ❌                | ✅          | ❌               | ✅             | ✅         | ✅        | ❌    |
| **Cameras**   | ✅     | ❌               | ❌                | ✅          | ✅               | ✅             | ✅         | ❌        | ✅    |
| **Expense**   | ✅     | ❌               | ✅                | ✅          | ✅               | ✅             | ✅         | ✅        | ✅    |
| **Fleet**     | ✅     | ❌               | ❌                | ✅          | ❌               | ✅             | ✅         | ✅        | ✅    |
| **HR**        | ✅     | ❌               | ✅                | ✅          | ✅               | ✅             | ✅         | ✅        | ❌    |
| **Notification**| ✅   | ❌               | ❌                | ✅          | ❌               | ✅             | ✅         | ✅        | ✅    |
| **Payroll**   | ✅     | ✅               | ✅                | ✅          | ✅               | ✅             | ✅         | ✅        | ✅    |
| **Planning**  | ✅     | ❌               | ✅                | ✅          | ❌               | ✅             | ✅         | ✅        | ✅    |
| **Recruitment**| ✅    | ❌               | ✅                | ✅          | ❌               | ✅             | ✅         | ✅        | ✅    |

### Notes sur Training

Training n'est **pas un module standalone**. Les modèles sont intégrés dans le module **HR** :
- `App\Modules\HR\Domain\Models\TrainingCourse`
- `App\Modules\HR\Domain\Models\TrainingEnrollment`
- `App\Modules\HR\Domain\Models\TrainingSession`

Le controller `TrainingController.php` est encore dans `App\Http\Controllers\Api\V1` (à migrer vers HR ou un sous-module dédié).

---

## 2. Controllers encore dans `App\Http\Controllers\Api\V1` à migrer

**Total : 90 controllers** non encore migrés vers l'architecture DDD modulaire.

### Liste complète

```
AbsenceController.php
AIWorkflowController.php
ApprovalController.php
AttendanceController.php
BankExportController.php
BiometricEnrollmentController.php
BillingController.php
BulkPaymentController.php
CabinetDocumentController.php
CabinetFolderController.php
CabinetShareController.php
CalendarSyncController.php
ClientEventController.php
CommunicationAnalyticsController.php
CompanyBrandingController.php
CompanyRequestController.php
ContractController.php
CotisationSimulationController.php
DashboardController.php
DemoUserController.php
DepartmentController.php
DeviceTokenController.php
EvaluationController.php
EmployeeController.php
EmployeeImportController.php
ExportController.php
ExpenseClaimController.php
FeatureFlagController.php
FleetController.php
GrowthAdminController.php
HealthController.php
HrController.php
HrReportController.php
InvitationController.php
JobPostingActionController.php
KioskController.php
LaunchReadinessController.php
LeavePolicyController.php
MetricsController.php
NotificationController.php
NotificationPreferenceController.php
OnboardingChecklistController.php
OnboardingController.php
OnboardingQrController.php
OnboardingStepController.php
OrgChartController.php
PartnerDashboardController.php
PaymentBatchController.php
PaymentDocumentController.php
PaymentWebhookController.php
PayrollController.php
PayrollCycleController.php
PayrollRunController.php
PaySlipController.php
PlanningController.php
PlatformCompanyFeatureController.php
PlatformCompanyHealthController.php
PlatformCompanyRequestController.php
PlatformCompanySubscriptionController.php
PlatformCountryDefaultsController.php
PlatformCrmPipelineController.php
PlatformMetricsOverviewController.php
PlatformPlanController.php
PositionController.php
PrivacyController.php
ProjectController.php
RecruitmentController.php
RoleAssignmentController.php
SalaryAdvanceController.php
SalaryComponentController.php
SalaryStructureController.php
ScheduleController.php
SelfServiceController.php
SelfServiceTrialController.php
SocialContributionController.php
SocialDeclarationController.php
SSO/SSOController.php
StripeWebhookController.php
TaskController.php
TaxSlabController.php
TrackingSyncController.php
TrainingController.php
TranslationCatalogController.php
UserEmployeeLinkController.php
VehicleAlertController.php
VehicleController.php
VehicleMaintenanceController.php
VehicleTripController.php
WebhookController.php
ZktecoController.php
```

---

## 3. Plan de migration prioritisé

### Priorité 1 — Modules DDD existants à compléter (Quick wins)

Ces modules ont déjà leur structure DDD ; il suffit d'ajouter les couches manquantes et de déplacer les controllers légacy.

| Priorité | Module     | Action                                                                 | Effort  |
|----------|------------|------------------------------------------------------------------------|---------|
| P1.1     | **Payroll**| Ajouter tests unitaires pour EmployeeLoan ; déplacer PayrollController, PayrollCycleController, PayrollRunController | Moyen   |
| P1.2     | **Absence**| Déplacer AbsenceController → `Absence/Interfaces/Api/V1/Controllers`   | Faible  |
| P1.3     | **Expense**| Déplacer ExpenseClaimController → `Expense/Interfaces/Api/V1/Controllers` | Faible  |
| P1.4     | **Attendance**| Déplacer AttendanceController → `Attendance/Interfaces/Api/V1`      | Faible  |
| P1.5     | **Recruitment**| Déplacer RecruitmentController → `Recruitment/Interfaces/Api/V1`   | Faible  |
| P1.6     | **Fleet**  | Ajouter `Domain/Exceptions`, `Application/DTOs` ; déplacer FleetController, VehicleController, VehicleTripController, VehicleMaintenanceController, VehicleAlertController | Moyen  |
| P1.7     | **Planning**| Ajouter `Application/DTOs` ; déplacer PlanningController -> `Planning/Interfaces/Api/V1` | Faible  |

### Priorité 2 — Compléter les couches DDD manquantes

| Priorité | Module        | Couches manquantes                                           |
|----------|---------------|--------------------------------------------------------------|
| P2.1     | **Billing**   | `Domain/Exceptions`, `Application/DTOs`, `Domain/Contracts` |
| P2.2     | **Cabinet**   | `Domain/Exceptions`, `Application/DTOs`, `Domain/Contracts` |
| P2.3     | **Cameras**   | `Domain/Exceptions`, `Domain/Contracts`, `Providers`        |
| P2.4     | **HR**        | `Domain/Contracts`                                           |
| P2.5     | **Notification**| `Domain/Exceptions`, `Application/DTOs`, `Domain/Contracts` |

### Priorité 3 — Modules à créer ou extraire

| Priorité | Module        | Action                                                               | Effort |
|----------|---------------|----------------------------------------------------------------------|--------|
| P3.1     | **Training**  | Extraire de HR → créer un module `Training` standalone avec Domain/Application/Infrastructure/Interfaces/Providers | Élevé |
| P3.2     | **Employee**  | Consolider EmployeeController, EmployeeImportController, SalaryStructureController, SalaryComponentController → module `HR` ou `Employee` | Élevé |
| P3.3     | **Platform**  | Déplacer tous les controllers `Platform*` dans un module `Platform` | Moyen  |
| P3.4     | **Onboarding**| Consolider OnboardingController, OnboardingChecklistController, OnboardingQrController, OnboardingStepController → module `Onboarding` | Moyen |

### Priorité 4 — Tests à ajouter

| Priorité | Module    | Fichier à créer                                |
|----------|-----------|------------------------------------------------|
| P4.1     | **HR**    | `tests/Feature/HR/HrControllerTest.php`        |
| P4.2     | **Cabinet**| `tests/Feature/Cabinet/CabinetDocumentControllerTest.php` |
| P4.3     | **Billing**| Compléter `BillingControllerTest.php` avec tests d'isolation tenant |
| P4.4     | **Payroll**| `tests/Feature/EmployeeLoanControllerTest.php` → ajouter `test_cross_tenant_loan_returns_404`, `test_disburse_requires_approved_status` |

---

## 4. Récapitulatif

| Indicateur                              | Valeur |
|-----------------------------------------|--------|
| Modules DDD actifs                      | 12     |
| Controllers légacy à migrer             | 90     |
| Modules avec `Domain/Contracts`         | 1/12 (Payroll uniquement) |
| Modules avec tests Feature dédiés       | 9/12   |
| Modules avec `Application/DTOs`         | 6/12   |
| Training (module standalone)            | Non — intégré dans HR |

**Priorité immédiate :** Compléter Payroll (Domain/Contracts pour tous les modules), ajouter les tests manquants, puis migrer les controllers légacy module par module en commençant par Absence et Expense (effort minimal, structure déjà en place).
