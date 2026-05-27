# Plan 33 — Documentation Professionnelle & API Production-Ready

**Date** : 2026-05-27
**Auteur** : Devin (analyse automatisee)
**Prerequis** : Plans 31 (Pointage/Taches Mobile) et 32 (Compte Employe Durable) livres et merges.

---

## 1. Resume Executif

Apres une analyse approfondie des **86 controllers**, **83 modeles**, **54 Resources**, **62 FormRequests**, **23 Policies**, **~250 routes** et **6 345 lignes OpenAPI**, voici le diagnostic :

| Indicateur | Valeur actuelle | Cible Plan 33 |
|---|---|---|
| Routes reelles | ~250 | ~250 (pas de nouvelles routes) |
| Routes documentees OpenAPI | ~103 | **250 (100%)** |
| Controllers avec inline `$request->validate()` | **48** | **0** |
| Controllers utilisant API Resources | 43 | **86 (100%)** |
| Controllers avec `$this->authorize()` | **6** | **86 (100%)** |
| Write paths avec `DB::transaction` | **11** | **~80+ (toutes les ecritures multi-table)** |
| FormRequest classes | 62 | **~120+ (chaque route POST/PUT/PATCH)** |
| Policies | 23 | **~30+ (chaque modele editable)** |
| Tests Feature | ~120 fichiers | ~120+ (pas de regression) |

**Verdict** : L'API est fonctionnellement riche (250 routes couvrant 20+ modules RH) mais **40% seulement est documente dans OpenAPI**, la validation inline persiste dans 48 controllers, et l'autorisation model-level est quasi absente. Le Plan 33 corrige ces 3 axes pour atteindre la production.

---

## 2. Audit Detaille — Etat Actuel Post Plans 31-32

### 2.1 Ce que Plans 31-32 ont apporte

**Plan 31 — Pointage Multi-Evenements & Taches Terrain** :
- `attendance_logs` accepte `work_type`, `punch_note`, `punch_meta`
- Sessions multiples par jour (`session_number`)
- `GET /attendance/today` renvoie `sessions` + `summary`
- `GET /tasks/today` pour les taches du jour
- Champs execution sur tasks (duree prevue/reelle, score, recurrence)

**Plan 32 — Compte Employe Durable** :
- `personal_email`, `recovery_email`, `personal_phone` sur profil
- `GET /me/career` — parcours professionnel durable
- Cabinet numerique resolu par `employee_id` (durable)
- Tests de garde sur profil, carriere, cabinet stats

### 2.2 API Resources — Couverture

**54 Resource classes existent** pour :
AbsenceResource, ApplicantResource, ApprovalRequestResource, ApprovalWorkflowResource, AttendanceLogResource, AttendanceTodayResource, AuditLogResource, BankExportResource, CabinetDocumentResource, CabinetFolderResource, CabinetShareResource, ContractAmendmentResource, ContractResource, DashboardSummaryResource, DepartmentResource, EmployeeResource, EvaluationResource, ExpenseClaimResource, FeaturePlanMatrixResource, InterviewResource, InvoiceResource, JobPostingResource, LeaveAccrualResource, LeaveBalanceResource, LeavePolicyResource, LoanResource, NotificationPreferenceResource, NotificationResource, OnboardingStepResource, PaySlipResource, PayrollResource, PayrollRunResource, PositionResource, ProjectResource, SalaryAdvanceResource, SalaryComponentResource, SalaryStructureResource, ScheduleResource, SiteResource, SocialContributionResource, SubscriptionResource, TaskCommentResource, TaskResource, TaxSlabResource, TrainingCourseResource, TrainingEnrollmentResource, TrainingSessionResource, UserInvitationResource, VehicleAlertResource, VehicleAssignmentResource, VehicleMaintenanceResource, VehicleResource, VehicleTripResource, WebhookEndpointResource.

**Controllers qui utilisent DEJA des Resources** (43/86) : AbsenceController, ApprovalController, AttendanceController, AuditLogController, AuthController, BankExportController, BillingController, CabinetDocumentController, ContractController, DepartmentController, EmployeeController, EmployeeLoanController, EvaluationController, ExpenseClaimController, InvitationController, JobPostingActionController, LeavePolicyController, MeController, NotificationController, NotificationPreferenceController, OnboardingStepController, PaySlipController, PayrollController, PayrollRunController, PositionController, ProjectController, RecruitmentController, SalaryAdvanceController, SalaryComponentController, SalaryStructureController, ScheduleController, SelfServiceController, SiteController, SocialContributionController, TaskController, TaxSlabController, TrainingController, VehicleAlertController, VehicleController, VehicleMaintenanceController, VehicleTripController, WebhookController.

**Controllers retournant ENCORE du raw JSON** (43 controllers) :
AIWorkflowController, AdvancedReportController, CabinetFolderController, CabinetShareController, CalendarSyncController, ClientEventController, CommunicationAnalyticsController, CompanyRequestController, CotisationSimulationController, DashboardController, DemoUserController, DeviceTokenController, EmployeeImportController, ExportController, FeatureFlagController, FleetController, HealthController, HrReportController, KioskController, LaunchReadinessController, MetricsController, NotificationStreamController, OnboardingChecklistController, OnboardingController, OrgChartController, PaymentWebhookController, PlanningController, PlatformAuthController, PlatformCompanyFeatureController, PlatformCompanyHealthController, PlatformCompanyRequestController, PlatformCompanySubscriptionController, PlatformMetricsOverviewController, PlatformPlanController, PredictionController, PrivacyController, SocialDeclarationController, TrackingSyncController, TranslationCatalogController, UserAuthController, UserEmployeeLinkController, ZktecoController.

> **Note** : Certains controllers (ex. DashboardController, HealthController, MetricsController) retournent des aggregats ou des donnees brutes ou le pattern Resource n'apporte pas de valeur. D'autres (ex. KioskController, ZktecoController, PlatformAuthController) meritent une normalisation.

### 2.3 FormRequests — Couverture

**62 FormRequest classes existent**. **48 controllers** utilisent encore `$request->validate()` inline.

**Top offenders (validations inline)** :
| Controller | Validations inline |
|---|---|
| TrainingController | 6 |
| RecruitmentController | 6 |
| KioskController | 6 |
| UserAuthController | 5 |
| ContractController | 5 |
| TaskController | 4 |
| ApprovalController | 4 |
| ZktecoController | 3 |
| VehicleController | 3 |
| PlatformAuthController | 3 |

### 2.4 Policies & Authorization

**23 Policies existent** mais **80+ controllers n'appellent JAMAIS `$this->authorize()`**.
L'autorisation repose principalement sur les middleware `api.manager`, `api.manager:principal,comptable`, etc., mais PAS sur des policies model-level.

**Policies existantes** : AbsencePolicy, ApprovalRequestPolicy, AttendancePolicy, BillingPolicy, CameraPolicy, ContractPolicy, DepartmentPolicy, EmployeePolicy, EvaluationPolicy, ExpenseClaimPolicy, ExportPolicy, FeatureFlagPolicy, InvoicePolicy, LoanPolicy, OnboardingPolicy, PayrollPolicy, PositionPolicy, RecruitmentPolicy, SchedulePolicy, SitePolicy, TrainingPolicy, VehiclePolicy, WebhookEndpointPolicy.

**Policies MANQUANTES pour des modeles editables** :
- CabinetDocumentPolicy, CabinetFolderPolicy, CabinetSharePolicy
- ProjectPolicy, TaskPolicy
- SalaryComponentPolicy, SalaryStructurePolicy, TaxSlabPolicy, SocialContributionPolicy
- PayrollRunPolicy, PaySlipPolicy, BankExportPolicy
- NotificationPolicy, NotificationPreferencePolicy
- KioskPolicy, ZktecoDevicePolicy
- CalendarConnectionPolicy

### 2.5 DB::transaction — Integrite Donnees

Seulement **5 controllers + 6 services** utilisent `DB::transaction`.
Pour une application RH/paie traitant des salaires et contrats, c'est insuffisant.

### 2.6 OpenAPI Documentation — Couverture

**Sur ~250 routes reelles, ~103 sont documentees dans openapi.yaml (41%).**

---

## 3. Catalogue des Use Cases par Module

Pour chaque module, je liste les use cases identifies, l'endpoint correspondant, et mon **verdict** :
- **OK** = endpoint existe, documente, Resource, FormRequest
- **PARTIEL** = endpoint existe mais manque doc/Resource/FormRequest/Policy
- **MANQUANT** = use case non couvert par un endpoint

---

### UC-01 : Authentification Employe

| # | Use Case | Endpoint | Verdict | Detail |
|---|---|---|---|---|
| 01.1 | Login email/password | `POST /auth/login` | **PARTIEL** | OpenAPI OK, FormRequest `LoginRequest` OK, mais controller a encore `$request->validate()` dans `register` |
| 01.2 | Login Google OAuth | `POST /auth/google/token` | **PARTIEL** | Non documente OpenAPI |
| 01.3 | Inscription self-service | `POST /auth/register` | **PARTIEL** | FormRequest `StoreRegistrationRequest` OK, pas dans OpenAPI |
| 01.4 | Voir mon profil | `GET /auth/me` | **OK** | OpenAPI OK, Resource OK |
| 01.5 | Mettre a jour profil | `PATCH /auth/profile` | **PARTIEL** | OpenAPI OK, FormRequest `UpdateProfileRequest` OK |
| 01.6 | Changer langue | `PATCH /auth/language` | **PARTIEL** | Non documente OpenAPI, validation inline |
| 01.7 | Changer mot de passe | `POST /auth/change-password` | **PARTIEL** | OpenAPI OK, FormRequest `ChangePasswordRequest` OK |
| 01.8 | Refresh token | `POST /auth/refresh-token` | **PARTIEL** | Non documente OpenAPI |
| 01.9 | Logout | `POST /auth/logout` | **OK** | OpenAPI OK |
| 01.10 | Enrolement biometrique | `GET/POST /auth/biometric-enrollment` | **PARTIEL** | OpenAPI OK, validation inline |

**Action Plan UC-01** :
- Ajouter `/auth/register`, `/auth/google/token`, `/auth/language`, `/auth/refresh-token` a OpenAPI
- Extraire validation inline de `AuthController::register`, `changePassword` vers FormRequests existants ou nouveaux

---

### UC-02 : Authentification Utilisateur Ordinaire (sans entreprise)

| # | Use Case | Endpoint | Verdict | Detail |
|---|---|---|---|---|
| 02.1 | Inscription user | `POST /user/register` | **PARTIEL** | Non documente OpenAPI, validation inline |
| 02.2 | Login user | `POST /user/login` | **PARTIEL** | Non documente OpenAPI, validation inline |
| 02.3 | Login Google | `POST /user/google-signin` | **PARTIEL** | Non documente OpenAPI, validation inline |
| 02.4 | Voir profil user | `GET /user/me` | **PARTIEL** | Non documente OpenAPI |
| 02.5 | Maj profil user | `PATCH /user/profile` | **PARTIEL** | Non documente OpenAPI, validation inline |
| 02.6 | Changer mdp user | `POST /user/change-password` | **PARTIEL** | Non documente OpenAPI, validation inline |
| 02.7 | Logout user | `POST /user/logout` | **PARTIEL** | Non documente OpenAPI |
| 02.8 | Mes liens employe | `GET /user/employee-links` | **PARTIEL** | Non documente OpenAPI |
| 02.9 | Lier user a employe | `POST /employees/link-user` | **PARTIEL** | Non documente OpenAPI, validation inline |
| 02.10 | Demande creation entreprise | `POST /user/company-requests` | **PARTIEL** | Non documente OpenAPI |

**Action Plan UC-02** :
- Documenter les 10 routes `/user/*` dans OpenAPI
- Extraire les 5 validations inline de `UserAuthController`
- Creer `UserEmployeeLinkPolicy` pour securiser le lien user-employe

---

### UC-03 : Authentification Super-Admin (Plateforme)

| # | Use Case | Endpoint | Verdict | Detail |
|---|---|---|---|---|
| 03.1 | Login super-admin | `POST /platform/auth/login` | **OK** | OpenAPI OK |
| 03.2 | Profil super-admin | `GET /platform/auth/me` | **OK** | OpenAPI OK |
| 03.3 | Logout super-admin | `POST /platform/auth/logout` | **OK** | OpenAPI OK |
| 03.4 | Setup 2FA | `POST /platform/auth/2fa/setup` | **PARTIEL** | Non documente OpenAPI |
| 03.5 | Activer 2FA | `POST /platform/auth/2fa/enable` | **PARTIEL** | Non documente OpenAPI |
| 03.6 | Desactiver 2FA | `POST /platform/auth/2fa/disable` | **PARTIEL** | Non documente OpenAPI |

**Action Plan UC-03** :
- Documenter les 3 routes 2FA dans OpenAPI
- Extraire validation inline de `PlatformAuthController`

---

### UC-04 : Gestion Employes

| # | Use Case | Endpoint | Verdict | Detail |
|---|---|---|---|---|
| 04.1 | Lister employes | `GET /employees` | **OK** | OpenAPI OK, Resource OK, FormRequest via controller |
| 04.2 | Creer employe | `POST /employees` | **PARTIEL** | OpenAPI OK, FormRequest `StoreEmployeeRequest` OK |
| 04.3 | Voir employe | `GET /employees/{employee}` | **OK** | OpenAPI OK, Resource OK |
| 04.4 | Modifier employe | `PUT /employees/{employee}` | **PARTIEL** | OpenAPI OK, FormRequest `UpdateEmployeeRequest` OK |
| 04.5 | Archiver employe | `POST /employees/{employee}/archive` | **PARTIEL** | OpenAPI OK, FormRequest `ArchiveEmployeeRequest` OK |
| 04.6 | Import CSV | `POST /employees/import` | **PARTIEL** | Non documente OpenAPI, validation inline |
| 04.7 | Template import | `GET /employees/import-template` | **PARTIEL** | Non documente OpenAPI |
| 04.8 | Estimations (daily summary, quick estimate, receipt) | `GET /employees/{employee}/daily-summary` etc. | **PARTIEL** | OpenAPI OK, FormRequests existent |

**Action Plan UC-04** :
- Documenter `/employees/import` et `/employees/import-template` dans OpenAPI
- S'assurer que EmployeePolicy est appele dans tous les endpoints du controller

---

### UC-05 : Pointage (Attendance)

| # | Use Case | Endpoint | Verdict | Detail |
|---|---|---|---|---|
| 05.1 | Check-in | `POST /attendance/check-in` | **OK** | OpenAPI OK, FormRequest OK, Resource OK |
| 05.2 | Check-out | `POST /attendance/check-out` | **OK** | OpenAPI OK, FormRequest OK |
| 05.3 | Pointage du jour | `GET /attendance/today` | **OK** | OpenAPI OK, FormRequest OK, Resource OK |
| 05.4 | Historique pointage | `GET /attendance` | **OK** | OpenAPI OK, FormRequest OK |
| 05.5 | Demander correction | `POST /attendance/corrections` | **OK** | OpenAPI OK, validation inline |
| 05.6 | Modifier pointage (manager) | `PUT /attendance/{attendanceLog}` | **OK** | OpenAPI OK, validation inline |
| 05.7 | Anomalies | `GET /attendance/anomalies` | **PARTIEL** | Non documente OpenAPI, FormRequest OK |
| 05.8 | Rapport mensuel | `GET /attendance/monthly-report` | **PARTIEL** | Non documente OpenAPI, FormRequest OK |

**Action Plan UC-05** :
- Documenter `/attendance/anomalies` et `/attendance/monthly-report` dans OpenAPI
- Extraire validation inline de `requestCorrection()` et `update()` vers FormRequests
- Ajouter `$this->authorize()` via AttendancePolicy

---

### UC-06 : Absences / Conges

| # | Use Case | Endpoint | Verdict | Detail |
|---|---|---|---|---|
| 06.1 | Lister absences | `GET /absences` | **PARTIEL** | **Non documente OpenAPI**, Resource OK, FormRequest OK |
| 06.2 | Demander absence | `POST /absences` | **PARTIEL** | **Non documente OpenAPI**, FormRequest OK |
| 06.3 | Voir absence | `GET /absences/{absence}` | **PARTIEL** | **Non documente OpenAPI**, Resource OK |
| 06.4 | Approuver absence | `PUT /absences/{absence}/approve` | **PARTIEL** | **Non documente OpenAPI** |
| 06.5 | Rejeter absence | `PUT /absences/{absence}/reject` | **PARTIEL** | **Non documente OpenAPI**, FormRequest OK |
| 06.6 | Annuler absence | `DELETE /absences/{absence}` | **PARTIEL** | **Non documente OpenAPI** |
| 06.7 | Soldes conges | `GET /me/leave-balances` | **PARTIEL** | **Non documente OpenAPI**, Resource OK |

**Action Plan UC-06** :
- Documenter toutes les routes `/absences/*` et `/me/leave-balances` dans OpenAPI (7 routes)
- Ajouter `$this->authorize()` dans AbsenceController via AbsencePolicy existante

---

### UC-07 : Avances sur Salaire

| # | Use Case | Endpoint | Verdict | Detail |
|---|---|---|---|---|
| 07.1 | Lister avances | `GET /salary-advances` | **PARTIEL** | **Non documente OpenAPI**, Resource OK, FormRequest OK |
| 07.2 | Demander avance | `POST /salary-advances` | **PARTIEL** | **Non documente OpenAPI**, FormRequest OK |
| 07.3 | Voir avance | `GET /salary-advances/{id}` | **PARTIEL** | **Non documente OpenAPI**, Resource OK |
| 07.4 | Approuver avance | `PUT /salary-advances/{id}/approve` | **PARTIEL** | **Non documente OpenAPI**, FormRequest OK |
| 07.5 | Rejeter avance | `PUT /salary-advances/{id}/reject` | **PARTIEL** | **Non documente OpenAPI**, FormRequest OK |
| 07.6 | Annuler avance | `DELETE /salary-advances/{id}` | **PARTIEL** | **Non documente OpenAPI** |

**Action Plan UC-07** :
- Documenter les 6 routes dans OpenAPI
- Controller deja bien structure avec FormRequests et Resources

---

### UC-08 : Paie (Payroll Legacy + Payroll Engine)

| # | Use Case | Endpoint | Verdict | Detail |
|---|---|---|---|---|
| 08.1 | Lister fiches paie | `GET /payrolls` | **PARTIEL** | Non documente OpenAPI, FormRequest OK |
| 08.2 | Creer fiche paie | `POST /payrolls` | **PARTIEL** | Non documente OpenAPI, FormRequest OK |
| 08.3 | Valider fiche paie | `PUT /payrolls/{id}/validate` | **PARTIEL** | Non documente OpenAPI, validation inline |
| 08.4 | Structures salariales CRUD | `/salary-structures/*` | **PARTIEL** | Non documente OpenAPI, validation inline |
| 08.5 | Composants salariaux CRUD | `/salary-components/*` | **PARTIEL** | Non documente OpenAPI, validation inline |
| 08.6 | Tranches impots CRUD | `/tax-slabs/*` | **PARTIEL** | Non documente OpenAPI, validation inline |
| 08.7 | Cotisations sociales CRUD | `/social-contributions/*` | **PARTIEL** | Non documente OpenAPI, validation inline |
| 08.8 | Cycles de paie (runs) | `/payroll-runs/*` | **PARTIEL** | Non documente OpenAPI, validation inline |
| 08.9 | Calcul cycle paie | `POST /payroll-runs/{id}/calculate` | **PARTIEL** | Non documente OpenAPI |
| 08.10 | Bulletins employe self-service | `GET /me/pay-slips` etc. | **PARTIEL** | Non documente OpenAPI, Resource OK |
| 08.11 | Bulletins manager | `GET /pay-slips` | **PARTIEL** | OpenAPI OK (partiellement) |
| 08.12 | PDF bulletin | `GET /pay-slips/{id}/pdf` | **PARTIEL** | Non documente OpenAPI |
| 08.13 | Envoi bulletins | `POST /payroll-runs/{id}/send-slips` | **PARTIEL** | Non documente OpenAPI |
| 08.14 | Export bancaire | `/bank-exports/*` | **PARTIEL** | Non documente OpenAPI, Resource OK |
| 08.15 | Declarations sociales | `/social-declarations/*` | **PARTIEL** | Non documente OpenAPI, validation inline |
| 08.16 | Simulation cotisations | `POST /cotisation-simulation` | **PARTIEL** | Non documente OpenAPI, validation inline |

**Action Plan UC-08** :
- Documenter les ~30 routes payroll dans OpenAPI (bloc le plus volumineux)
- Extraire les validations inline des 7+ controllers paie
- Creer PayrollRunPolicy, PaySlipPolicy, BankExportPolicy, SalaryComponentPolicy, SalaryStructurePolicy, TaxSlabPolicy, SocialContributionPolicy
- Ajouter `DB::transaction` dans les cycles de paie (calculate, validate, cancel)

---

### UC-09 : Politiques de Conges

| # | Use Case | Endpoint | Verdict | Detail |
|---|---|---|---|---|
| 09.1 | Lister politiques | `GET /leave-policies` | **PARTIEL** | Non documente OpenAPI, validation inline |
| 09.2 | Creer politique | `POST /leave-policies` | **PARTIEL** | Non documente OpenAPI, FormRequest OK |
| 09.3 | Voir politique | `GET /leave-policies/{id}` | **PARTIEL** | Non documente OpenAPI |
| 09.4 | Modifier politique | `PUT /leave-policies/{id}` | **PARTIEL** | Non documente OpenAPI, validation inline |
| 09.5 | Supprimer politique | `DELETE /leave-policies/{id}` | **PARTIEL** | Non documente OpenAPI |
| 09.6 | Soldes globaux | `GET /leave-balances` | **PARTIEL** | Non documente OpenAPI |
| 09.7 | Accruals | `GET /leave-accruals` | **PARTIEL** | Non documente OpenAPI |
| 09.8 | Creer accrual | `POST /leave-accruals` | **PARTIEL** | Non documente OpenAPI, validation inline |

**Action Plan UC-09** :
- Documenter les 8 routes dans OpenAPI
- Extraire 3 validations inline restantes

---

### UC-10 : Contrats

| # | Use Case | Endpoint | Verdict | Detail |
|---|---|---|---|---|
| 10.1 | Lister contrats | `GET /contracts` | **PARTIEL** | Non documente OpenAPI |
| 10.2 | Creer contrat | `POST /contracts` | **PARTIEL** | Non documente OpenAPI, FormRequest OK |
| 10.3 | Contrats expirants | `GET /contracts/expiring` | **PARTIEL** | Non documente OpenAPI |
| 10.4 | Voir contrat | `GET /contracts/{id}` | **PARTIEL** | Non documente OpenAPI, Resource OK |
| 10.5 | Modifier contrat | `PUT /contracts/{id}` | **PARTIEL** | Non documente OpenAPI, validation inline |
| 10.6 | Activer contrat | `POST /contracts/{id}/activate` | **PARTIEL** | Non documente OpenAPI |
| 10.7 | Suspendre contrat | `POST /contracts/{id}/suspend` | **PARTIEL** | Non documente OpenAPI |
| 10.8 | Resilier contrat | `POST /contracts/{id}/terminate` | **PARTIEL** | Non documente OpenAPI, validation inline |
| 10.9 | Renouveler contrat | `POST /contracts/{id}/renew` | **PARTIEL** | Non documente OpenAPI, validation inline |
| 10.10 | Avenants | `GET/POST /contracts/{id}/amendments` | **PARTIEL** | Non documente OpenAPI, validation inline |
| 10.11 | PDF contrat | `GET /contracts/{id}/generate-pdf` | **PARTIEL** | Non documente OpenAPI |
| 10.12 | Mes contrats | `GET /me/contracts` | **PARTIEL** | Non documente OpenAPI |

**Action Plan UC-10** :
- Documenter les 12 routes contrats dans OpenAPI
- Extraire les 5 validations inline
- Ajouter `$this->authorize()` via ContractPolicy (existante)
- `DB::transaction` sur activate/suspend/terminate/renew

---

### UC-11 : Recrutement (ATS)

| # | Use Case | Endpoint | Verdict | Detail |
|---|---|---|---|---|
| 11.1 | Lister offres | `GET /recruitment/jobs` | **PARTIEL** | Non documente OpenAPI, validation inline |
| 11.2 | Creer offre | `POST /recruitment/jobs` | **PARTIEL** | Non documente OpenAPI, FormRequest OK |
| 11.3 | Voir offre | `GET /recruitment/jobs/{id}` | **PARTIEL** | Non documente OpenAPI |
| 11.4 | Modifier offre | `PUT /recruitment/jobs/{id}` | **PARTIEL** | Non documente OpenAPI, validation inline |
| 11.5 | Publier offre | `POST /recruitment/jobs/{id}/publish` | **PARTIEL** | Non documente OpenAPI |
| 11.6 | Cloturer offre | `POST /recruitment/jobs/{id}/close` | **PARTIEL** | Non documente OpenAPI |
| 11.7 | Supprimer offre | `DELETE /recruitment/jobs/{id}` | **PARTIEL** | Non documente OpenAPI |
| 11.8 | Lister candidats | `GET /recruitment/jobs/{id}/applicants` | **PARTIEL** | Non documente OpenAPI, validation inline |
| 11.9 | Ajouter candidat | `POST /recruitment/jobs/{id}/applicants` | **PARTIEL** | Non documente OpenAPI, FormRequest OK |
| 11.10 | Modifier candidat | `PUT /recruitment/applicants/{id}` | **PARTIEL** | Non documente OpenAPI, validation inline |
| 11.11 | Statut candidat | `PATCH /recruitment/applicants/{id}/status` | **PARTIEL** | Non documente OpenAPI |
| 11.12 | Voir candidat | `GET /recruitment/applicants/{id}` | **PARTIEL** | Non documente OpenAPI |
| 11.13 | Supprimer candidat | `DELETE /recruitment/applicants/{id}` | **PARTIEL** | Non documente OpenAPI |
| 11.14 | Planifier entretien | `POST /recruitment/applicants/{id}/interviews` | **PARTIEL** | Non documente OpenAPI, validation inline |
| 11.15 | Modifier entretien | `PUT /recruitment/interviews/{id}` | **PARTIEL** | Non documente OpenAPI, validation inline |
| 11.16 | Feedback entretien | `PATCH /recruitment/interviews/{id}/feedback` | **PARTIEL** | Non documente OpenAPI |
| 11.17 | Supprimer entretien | `DELETE /recruitment/interviews/{id}` | **PARTIEL** | Non documente OpenAPI |

**Action Plan UC-11** :
- Documenter les 17 routes recrutement dans OpenAPI
- Extraire les 6 validations inline de `RecruitmentController`
- Ajouter `$this->authorize()` via RecruitmentPolicy (existante)

---

### UC-12 : Formation

| # | Use Case | Endpoint | Verdict | Detail |
|---|---|---|---|---|
| 12.1 | Lister formations | `GET /training/courses` | **PARTIEL** | Non documente OpenAPI, validation inline |
| 12.2 | Creer formation | `POST /training/courses` | **PARTIEL** | Non documente OpenAPI, FormRequest OK |
| 12.3 | Voir formation | `GET /training/courses/{id}` | **PARTIEL** | Non documente OpenAPI, Resource OK |
| 12.4 | Modifier formation | `PUT /training/courses/{id}` | **PARTIEL** | Non documente OpenAPI, validation inline |
| 12.5 | Sessions | `GET/POST /training/courses/{id}/sessions` | **PARTIEL** | Non documente OpenAPI, FormRequest OK |
| 12.6 | Modifier session | `PUT /training/sessions/{id}` | **PARTIEL** | Non documente OpenAPI, validation inline |
| 12.7 | Inscription | `POST /training/sessions/{id}/enroll` | **PARTIEL** | Non documente OpenAPI, validation inline |
| 12.8 | Modifier inscription | `PUT /training/enrollments/{id}` | **PARTIEL** | Non documente OpenAPI, validation inline |
| 12.9 | Mes formations | `GET /me/trainings` | **PARTIEL** | Non documente OpenAPI |
| 12.10 | Auto-inscription | `POST /me/trainings/{id}/enroll` | **PARTIEL** | Non documente OpenAPI |

**Action Plan UC-12** :
- Documenter les 10 routes formation dans OpenAPI
- Extraire les 6 validations inline de `TrainingController`
- Ajouter `$this->authorize()` via TrainingPolicy (existante)

---

### UC-13 : Prets Employes

| # | Use Case | Endpoint | Verdict | Detail |
|---|---|---|---|---|
| 13.1 | Lister prets | `GET /loans` | **PARTIEL** | Non documente OpenAPI, Resource OK |
| 13.2 | Demander pret | `POST /loans` | **PARTIEL** | Non documente OpenAPI, FormRequest OK |
| 13.3 | Voir pret | `GET /loans/{id}` | **PARTIEL** | Non documente OpenAPI, Resource OK |
| 13.4 | Approuver pret | `PUT /loans/{id}/approve` | **PARTIEL** | Non documente OpenAPI |
| 13.5 | Decaisser pret | `PUT /loans/{id}/disburse` | **PARTIEL** | Non documente OpenAPI |
| 13.6 | Mes prets | `GET /me/loans` | **PARTIEL** | Non documente OpenAPI |
| 13.7 | Mes remboursements | `GET /me/loans/{id}/repayments` | **PARTIEL** | Non documente OpenAPI |

**Action Plan UC-13** :
- Documenter les 7 routes dans OpenAPI
- Ajouter `$this->authorize()` via LoanPolicy (existante)
- `DB::transaction` sur approve/disburse

---

### UC-14 : Notes de Frais

| # | Use Case | Endpoint | Verdict | Detail |
|---|---|---|---|---|
| 14.1 | Lister notes frais | `GET /expense-claims` | **PARTIEL** | Non documente OpenAPI, Resource OK |
| 14.2 | Creer note frais | `POST /expense-claims` | **PARTIEL** | Non documente OpenAPI, FormRequest OK |
| 14.3 | Voir note frais | `GET /expense-claims/{id}` | **PARTIEL** | Non documente OpenAPI, Resource OK |
| 14.4 | Soumettre note frais | `PUT /expense-claims/{id}/submit` | **PARTIEL** | Non documente OpenAPI |
| 14.5 | Approuver | `PUT /expense-claims/{id}/approve` | **PARTIEL** | Non documente OpenAPI |
| 14.6 | Rejeter | `PUT /expense-claims/{id}/reject` | **PARTIEL** | Non documente OpenAPI |

**Action Plan UC-14** :
- Documenter les 6 routes dans OpenAPI
- Ajouter `$this->authorize()` via ExpenseClaimPolicy (existante)

---

### UC-15 : Approbations Generiques

| # | Use Case | Endpoint | Verdict | Detail |
|---|---|---|---|---|
| 15.1 | Demandes en attente | `GET /approvals/pending` | **PARTIEL** | Non documente OpenAPI, Resource OK |
| 15.2 | Approuver | `POST /approvals/{id}/approve` | **PARTIEL** | Non documente OpenAPI, validation inline |
| 15.3 | Rejeter | `POST /approvals/{id}/reject` | **PARTIEL** | Non documente OpenAPI, validation inline |
| 15.4 | Historique | `GET /approvals/history` | **PARTIEL** | Non documente OpenAPI |
| 15.5 | Workflows CRUD | `/approval-workflows/*` | **PARTIEL** | Non documente OpenAPI, validation inline |

**Action Plan UC-15** :
- Documenter les 8 routes approvals dans OpenAPI
- Extraire 4 validations inline de `ApprovalController`

---

### UC-16 : Referentiels RH

| # | Use Case | Endpoint | Verdict | Detail |
|---|---|---|---|---|
| 16.1 | Departements CRUD | `/departments/*` | **PARTIEL** | Non documente OpenAPI, Resource + FormRequest OK |
| 16.2 | Postes CRUD | `/positions/*` | **PARTIEL** | Non documente OpenAPI, Resource + FormRequest OK |
| 16.3 | Sites CRUD | `/sites/*` | **PARTIEL** | Non documente OpenAPI, Resource + FormRequest OK |
| 16.4 | Plannings CRUD | `/schedules/*` | **PARTIEL** | Non documente OpenAPI, Resource + FormRequest OK |

**Action Plan UC-16** :
- Documenter les ~20 routes referentiels dans OpenAPI
- Ajouter `$this->authorize()` via Policies existantes (DepartmentPolicy, PositionPolicy, SitePolicy, SchedulePolicy)

---

### UC-17 : Projets & Taches

| # | Use Case | Endpoint | Verdict | Detail |
|---|---|---|---|---|
| 17.1 | Projets CRUD | `/projects/*` | **OK** | OpenAPI OK, Resource OK, validation inline |
| 17.2 | Taches CRUD | `/tasks/*` | **OK** | OpenAPI OK, Resource OK, validation inline |
| 17.3 | Commentaires tache | `POST /tasks/{id}/comments` | **OK** | OpenAPI OK, Resource OK |
| 17.4 | Taches du jour | `GET /tasks/today` | **PARTIEL** | **Non documente OpenAPI** |

**Action Plan UC-17** :
- Documenter `/tasks/today` dans OpenAPI
- Extraire validations inline (3 dans ProjectController, 4 dans TaskController)
- Creer ProjectPolicy et TaskPolicy

---

### UC-18 : Evaluations

| # | Use Case | Endpoint | Verdict | Detail |
|---|---|---|---|---|
| 18.1 | Evaluations CRUD | `/evaluations/*` | **OK** | OpenAPI OK, Resource OK, FormRequests OK |
| 18.2 | Soumettre evaluation | `PUT /evaluations/{id}/submit` | **OK** | OpenAPI OK |
| 18.3 | Accuser reception | `PUT /evaluations/{id}/acknowledge` | **OK** | OpenAPI OK |

**Action Plan UC-18** :
- Module bien couvert. Ajouter `$this->authorize()` via EvaluationPolicy (existante)

---

### UC-19 : Cabinet Numerique (Placard)

| # | Use Case | Endpoint | Verdict | Detail |
|---|---|---|---|---|
| 19.1 | Dossiers CRUD | `/cabinet/folders/*` | **PARTIEL** | Non documente OpenAPI, FormRequests OK |
| 19.2 | Documents CRUD | `/cabinet/documents/*` | **PARTIEL** | Non documente OpenAPI, Resource OK, FormRequests OK |
| 19.3 | Telecharger document | `GET /cabinet/documents/{id}/download` | **PARTIEL** | Non documente OpenAPI |
| 19.4 | Deplacer document | `PATCH /cabinet/documents/{id}/move` | **PARTIEL** | Non documente OpenAPI, FormRequest OK |
| 19.5 | Partages CRUD | `/cabinet/shares/*` | **PARTIEL** | Non documente OpenAPI, FormRequest OK |
| 19.6 | Acces partage public | `GET /cabinet/shared/{token}` | **PARTIEL** | Non documente OpenAPI |
| 19.7 | Stats placard | `GET /cabinet/stats` | **PARTIEL** | Non documente OpenAPI |

**Action Plan UC-19** :
- Documenter les ~12 routes cabinet dans OpenAPI
- Creer CabinetDocumentPolicy, CabinetFolderPolicy, CabinetSharePolicy

---

### UC-20 : Notifications

| # | Use Case | Endpoint | Verdict | Detail |
|---|---|---|---|---|
| 20.1 | Lister notifications | `GET /notifications` | **OK** | OpenAPI OK, Resource OK |
| 20.2 | Non lues | `GET /notifications/unread` | **PARTIEL** | Non documente OpenAPI |
| 20.3 | Marquer lue | `PUT /notifications/{id}/read` | **OK** | OpenAPI OK |
| 20.4 | Tout marquer lu | `PUT /notifications/read-all` | **OK** | OpenAPI OK |
| 20.5 | Supprimer | `DELETE /notifications/{id}` | **OK** | OpenAPI OK |
| 20.6 | Stream SSE | `GET /notifications/stream` | **PARTIEL** | Non documente OpenAPI |
| 20.7 | Preferences | `GET/PATCH /notification-preferences` | **OK** | OpenAPI OK, Resource OK |
| 20.8 | Push tokens | `POST/DELETE /device-tokens` | **PARTIEL** | Non documente OpenAPI, validation inline |
| 20.9 | Test push | `POST /push-notifications/send` | **PARTIEL** | Non documente OpenAPI |

**Action Plan UC-20** :
- Documenter `/notifications/unread`, `/notifications/stream`, `/device-tokens/*`, `/push-notifications/send`
- Extraire validations inline de DeviceTokenController

---

### UC-21 : Dashboard & Rapports

| # | Use Case | Endpoint | Verdict | Detail |
|---|---|---|---|---|
| 21.1 | Resume dashboard | `GET /dashboard/summary` | **PARTIEL** | Non documente OpenAPI |
| 21.2 | Activite recente | `GET /dashboard/recent-activity` | **PARTIEL** | Non documente OpenAPI |
| 21.3 | KPI | `GET /dashboard/kpi` | **PARTIEL** | Non documente OpenAPI |
| 21.4 | Rapport effectifs | `GET /reports/headcount` | **PARTIEL** | Non documente OpenAPI |
| 21.5 | Rapport turnover | `GET /reports/turnover` | **PARTIEL** | Non documente OpenAPI |
| 21.6 | Rapport absenteisme | `GET /reports/absenteeism` | **PARTIEL** | Non documente OpenAPI |
| 21.7 | Rapport masse salariale | `GET /reports/payroll-summary` | **PARTIEL** | Non documente OpenAPI |
| 21.8 | Rapport heures sup | `GET /reports/overtime` | **PARTIEL** | Non documente OpenAPI |
| 21.9 | Pipeline recrutement | `GET /reports/recruitment-pipeline` | **PARTIEL** | Non documente OpenAPI |
| 21.10 | Completion formation | `GET /reports/training-completion` | **PARTIEL** | Non documente OpenAPI |
| 21.11 | Resume prets | `GET /reports/loan-summary` | **PARTIEL** | Non documente OpenAPI |
| 21.12 | Demographics | `GET /reports/demographics` | **PARTIEL** | Non documente OpenAPI |
| 21.13 | Analyse couts | `GET /reports/cost-analysis` | **PARTIEL** | Non documente OpenAPI |

**Action Plan UC-21** :
- Documenter les 13 routes dashboard/reports dans OpenAPI
- Les controllers retournent du raw JSON — evaluer si des Resources sont utiles ici (probablement non, ce sont des aggregats)

---

### UC-22 : Exports

| # | Use Case | Endpoint | Verdict | Detail |
|---|---|---|---|---|
| 22.1-22.8 | Exports (employees, attendance, pay-slips, absences, training, contracts, vehicles, history) | `GET /export/*` | **PARTIEL** | Non documente OpenAPI, validation inline, FormRequests partiels |

**Action Plan UC-22** :
- Documenter les 8 routes export dans OpenAPI
- Extraire 3 validations inline de ExportController

---

### UC-23 : Flotte Vehicules

| # | Use Case | Endpoint | Verdict | Detail |
|---|---|---|---|---|
| 23.1-23.12 | Vehicles CRUD, position, trips, alerts, maintenance, assign/unassign, assignments | `/vehicles/*` | **OK** | OpenAPI OK, Resources OK |
| 23.13-23.15 | Fleet dashboard (overview, live-map, reports) | `/fleet/*` | **OK** | OpenAPI OK |
| 23.16-23.18 | Traccar sync | `/tracking/*` | **OK** | OpenAPI OK |

**Action Plan UC-23** :
- Module bien documente. Extraire 3 validations inline de VehicleController et 2 de VehicleMaintenanceController
- Ajouter `$this->authorize()` via VehiclePolicy (existante)

---

### UC-24 : Organigramme

| # | Use Case | Endpoint | Verdict | Detail |
|---|---|---|---|---|
| 24.1 | Organigramme complet | `GET /org-chart` | **PARTIEL** | Non documente OpenAPI |
| 24.2 | Subordonnes | `GET /org-chart/{employee}/subordinates` | **PARTIEL** | Non documente OpenAPI |
| 24.3 | Chaine hierarchique | `GET /org-chart/{employee}/manager-chain` | **PARTIEL** | Non documente OpenAPI |

**Action Plan UC-24** :
- Documenter les 3 routes dans OpenAPI

---

### UC-25 : Billing & Abonnement

| # | Use Case | Endpoint | Verdict | Detail |
|---|---|---|---|---|
| 25.1 | Voir abonnement | `GET /billing/subscription` | **PARTIEL** | Non documente OpenAPI, Resource OK |
| 25.2 | Upgrade | `POST /billing/subscription/upgrade` | **PARTIEL** | Non documente OpenAPI, FormRequest OK |
| 25.3 | Annuler | `POST /billing/subscription/cancel` | **PARTIEL** | Non documente OpenAPI, FormRequest OK |
| 25.4 | Renouveler | `POST /billing/subscription/renew` | **PARTIEL** | Non documente OpenAPI |
| 25.5 | Factures | `GET /billing/invoices` | **PARTIEL** | Non documente OpenAPI, Resource OK |
| 25.6 | Detail facture | `GET /billing/invoices/{id}` | **PARTIEL** | Non documente OpenAPI |
| 25.7 | PDF facture | `GET /billing/invoices/{id}/pdf` | **PARTIEL** | Non documente OpenAPI |
| 25.8 | Webhook Stripe | `POST /webhooks/stripe` | **PARTIEL** | Non documente OpenAPI |
| 25.9 | Webhook Chargily | `POST /webhooks/chargily` | **PARTIEL** | Non documente OpenAPI |

**Action Plan UC-25** :
- Documenter les 9 routes billing dans OpenAPI
- Ajouter `DB::transaction` sur upgrade/cancel/renew
- Ajouter `$this->authorize()` via BillingPolicy (existante)

---

### UC-26 : Onboarding

| # | Use Case | Endpoint | Verdict | Detail |
|---|---|---|---|---|
| 26.1 | Voir invitation | `GET /onboarding/invitation/{token}` | **PARTIEL** | Non documente OpenAPI |
| 26.2 | Activer invitation | `POST /onboarding/invitation/{token}/activate` | **PARTIEL** | Non documente OpenAPI |
| 26.3 | Checklist employe | `GET /onboarding/checklist` | **PARTIEL** | Non documente OpenAPI |
| 26.4 | Checklist setup | `GET /onboarding-setup/checklist` | **PARTIEL** | Non documente OpenAPI, Resource OK |
| 26.5 | Progression setup | `GET /onboarding-setup/progress` | **PARTIEL** | Non documente OpenAPI |
| 26.6 | Completer etape | `PATCH /onboarding-setup/{stepKey}/complete` | **PARTIEL** | Non documente OpenAPI |
| 26.7 | Ignorer etape | `PATCH /onboarding-setup/{stepKey}/skip` | **PARTIEL** | Non documente OpenAPI |

**Action Plan UC-26** :
- Documenter les 7 routes onboarding dans OpenAPI

---

### UC-27 : Webhooks & Audit

| # | Use Case | Endpoint | Verdict | Detail |
|---|---|---|---|---|
| 27.1 | Evenements disponibles | `GET /webhooks/events` | **PARTIEL** | Non documente OpenAPI |
| 27.2 | Webhooks CRUD | `/webhooks/*` | **PARTIEL** | Non documente OpenAPI, Resource + FormRequests OK |
| 27.3 | Audit logs | `GET /audit-logs` | **PARTIEL** | Non documente OpenAPI, Resource OK |
| 27.4 | Export audit CSV | `GET /audit-logs/export-csv` | **PARTIEL** | Non documente OpenAPI |
| 27.5 | Detail audit | `GET /audit-logs/{id}` | **PARTIEL** | Non documente OpenAPI, Resource OK |

**Action Plan UC-27** :
- Documenter les 8 routes dans OpenAPI

---

### UC-28 : SSO

| # | Use Case | Endpoint | Verdict | Detail |
|---|---|---|---|---|
| 28.1 | Providers | `GET /sso/providers` | **PARTIEL** | Non documente OpenAPI |
| 28.2 | Status | `GET /sso/status` | **PARTIEL** | Non documente OpenAPI |
| 28.3 | Configure | `POST /sso/configure` | **PARTIEL** | Non documente OpenAPI, validation inline |
| 28.4 | Disable | `DELETE /sso/disable` | **PARTIEL** | Non documente OpenAPI |
| 28.5 | Callbacks SAML/OIDC | `POST/GET /sso/saml/{id}/callback` etc. | **PARTIEL** | Non documente OpenAPI |

**Action Plan UC-28** :
- Documenter les 5 routes SSO dans OpenAPI

---

### UC-29 : Integrations (Calendar, ZKTeco, Kiosk)

| # | Use Case | Endpoint | Verdict | Detail |
|---|---|---|---|---|
| 29.1 | Calendar sync | `/calendar/*` | **PARTIEL** | Non documente OpenAPI, validation inline |
| 29.2 | ZKTeco CRUD + sync | `/zkteco/*` | **PARTIEL** | Non documente OpenAPI, validation inline |
| 29.3 | Kiosk extended | `/kiosks/{dc}/employee-info` etc. | **PARTIEL** | Non documente OpenAPI, validation inline |

**Action Plan UC-29** :
- Documenter les ~15 routes integrations dans OpenAPI
- Extraire validation inline de CalendarSyncController (2), ZktecoController (3), KioskController (6)

---

### UC-30 : Predictions IA & Planning

| # | Use Case | Endpoint | Verdict | Detail |
|---|---|---|---|---|
| 30.1 | Prediction turnover | `GET /predictions/turnover` | **PARTIEL** | Non documente OpenAPI |
| 30.2 | Prediction absenteisme | `GET /predictions/absenteeism` | **PARTIEL** | Non documente OpenAPI |
| 30.3 | Notifications proactives | `GET /predictions/notifications` | **PARTIEL** | Non documente OpenAPI |
| 30.4 | Optimisation planning hebdo | `GET /planning/weekly-optimization` | **PARTIEL** | Non documente OpenAPI |
| 30.5 | Reequilibrage shifts | `GET /planning/shift-rebalancing` | **PARTIEL** | Non documente OpenAPI |

**Action Plan UC-30** :
- Documenter les 5 routes dans OpenAPI

---

### UC-31 : Features & i18n

| # | Use Case | Endpoint | Verdict | Detail |
|---|---|---|---|---|
| 31.1 | Feature manifest | `GET /features/manifest` | **PARTIEL** | Non documente OpenAPI |
| 31.2 | Compatibilite version | `GET /features/compatible/{version}` | **PARTIEL** | Non documente OpenAPI |
| 31.3 | Feature check | `GET /features/{key}` | **PARTIEL** | Non documente OpenAPI |
| 31.4 | Feature flags matrix | `GET/PUT /feature-flags/matrix` | **PARTIEL** | Non documente OpenAPI |
| 31.5 | Check feature flag | `GET /feature-flags/check/{key}` | **PARTIEL** | Non documente OpenAPI |
| 31.6 | Catalogue traductions | `GET /i18n/catalog` | **PARTIEL** | Non documente OpenAPI |
| 31.7 | Traductions locale | `GET /i18n/catalog/{locale}` | **PARTIEL** | Non documente OpenAPI |

**Action Plan UC-31** :
- Documenter les 7 routes dans OpenAPI

---

### UC-32 : Self-Service Employe (agregation /me/*)

| # | Use Case | Endpoint | Verdict | Detail |
|---|---|---|---|---|
| 32.1 | Mon resume quotidien | `GET /me/daily-summary` | **OK** | OpenAPI OK |
| 32.2 | Mon estimation rapide | `GET /me/quick-estimate` | **OK** | OpenAPI OK |
| 32.3 | Mon resume mensuel | `GET /me/monthly-summary` | **OK** | OpenAPI OK |
| 32.4 | Ma carriere | `GET /me/career` | **OK** | OpenAPI OK |
| 32.5 | Mes bulletins | `GET /me/pay-slips` | **PARTIEL** | Non documente OpenAPI |
| 32.6 | Mes conges | `GET /me/leave-balances` | **PARTIEL** | Non documente OpenAPI |
| 32.7 | Mes contrats | `GET /me/contracts` | **PARTIEL** | Non documente OpenAPI |
| 32.8 | Mes formations | `GET /me/trainings` | **PARTIEL** | Non documente OpenAPI |
| 32.9 | Mes prets | `GET /me/loans` | **PARTIEL** | Non documente OpenAPI |

**Action Plan UC-32** :
- Documenter les 5 routes `/me/*` manquantes dans OpenAPI

---

### UC-33 : Use Cases MANQUANTS (pas d'endpoint)

| # | Use Case | Impact | Recommandation |
|---|---|---|---|
| 33.1 | **Reset mot de passe (forgot password)** | CRITIQUE — aucun utilisateur ne peut recuperer son compte | Ajouter `POST /auth/forgot-password` + `POST /auth/reset-password` |
| 33.2 | **Supprimer notification** individuelle par employe | FAIBLE — existe dans rh.php mais route dupliquee dans dashboard.php avec signature differente | Harmoniser les 2 fichiers route |
| 33.3 | **Suppression document cabinet par manager RH** | MOYEN — manager peut vouloir supprimer un doc d'un employe | Verifier scope policy |
| 33.4 | **Historique connexions utilisateur** | MOYEN — securite/audit | Considerer `GET /auth/sessions` |
| 33.5 | **Revoquer toutes les sessions** | MOYEN — securite | Considerer `POST /auth/revoke-all` |
| 33.6 | **Export PDF masse (multi-bulletins)** | MOYEN — manager veut un ZIP de tous les bulletins d'un mois | Considerer `POST /payroll-runs/{id}/export-all-pdf` |
| 33.7 | **Annuler pret** | FAIBLE — employe peut vouloir annuler avant decaissement | Considerer `DELETE /loans/{id}` |
| 33.8 | **Rejeter pret** | FAIBLE | Considerer `PUT /loans/{id}/reject` |
| 33.9 | **Supprimer note de frais** | FAIBLE | Considerer `DELETE /expense-claims/{id}` |
| 33.10 | **Tableau de bord employe** | MOYEN — self-service dashboard | `/me/dashboard` avec resume pointage + conges + bulletins |

---

## 4. Plan d'Action — 8 Iterations

### Iteration 33.1 — OpenAPI Documentation Bloc 1 : Core RH (~150 routes)
**Effort** : ~4h
**Contenu** :
- Absences (7 routes)
- Salary Advances (6 routes)
- Leave Policies (8 routes)
- Contracts (12 routes)
- HR Referentials — departments, positions, sites, schedules (20 routes)
- Self-service `/me/*` manquants (5 routes)
- Attendance manquants (anomalies, monthly-report)
- Tasks today

### Iteration 33.2 — OpenAPI Documentation Bloc 2 : Payroll Engine + Billing (~40 routes)
**Effort** : ~3h
**Contenu** :
- Payrolls legacy (7 routes)
- Payroll runs (7 routes)
- Salary structures/components, tax slabs, social contributions (16 routes)
- Bank exports (3 routes)
- Social declarations (3 routes)
- Cotisation simulation (1 route)
- Billing/subscription (7 routes)
- Payment webhooks (2 routes)

### Iteration 33.3 — OpenAPI Documentation Bloc 3 : Modules Etendus (~60 routes)
**Effort** : ~3h
**Contenu** :
- Recrutement (17 routes)
- Formation (10 routes)
- Prets (7 routes)
- Notes de frais (6 routes)
- Approbations (8 routes)
- Cabinet numerique (12 routes)

### Iteration 33.4 — OpenAPI Documentation Bloc 4 : Admin, Integrations, Divers (~50 routes)
**Effort** : ~3h
**Contenu** :
- Dashboard & Reports (13 routes)
- Exports (8 routes)
- Org chart (3 routes)
- Onboarding (7 routes)
- Webhooks & Audit (8 routes)
- SSO (5 routes)
- Integrations : calendar, ZKTeco, kiosk, device-tokens (15 routes)
- Predictions & Planning (5 routes)
- Features & i18n (7 routes)
- User module (10 routes)
- Platform 2FA (3 routes)
- Demo users (1 route)

### Iteration 33.5 — FormRequests : Extraction Complete des Validations Inline
**Effort** : ~4h
**Contenu** :
- Extraire les ~100 validations inline restantes des 48 controllers
- Organiser dans les sous-dossiers FormRequest existants
- Priorite : TrainingController (6), RecruitmentController (6), KioskController (6), UserAuthController (5), ContractController (5)

### Iteration 33.6 — Policies & Authorization Model-Level
**Effort** : ~3h
**Contenu** :
- Creer les ~10 Policies manquantes (Cabinet, Project, Task, Salary*, PayrollRun, etc.)
- Injecter `$this->authorize()` dans les 80+ controllers
- Couverture cible : chaque operation CRUD verifie l'autorisation model-level

### Iteration 33.7 — DB::transaction & Integrite Donnees
**Effort** : ~2h
**Contenu** :
- Identifier toutes les operations multi-table
- Wrapper dans `DB::transaction` : payroll calculate/validate/cancel, contract activate/suspend/terminate/renew, loan approve/disburse, billing upgrade/cancel
- Ajouter les lock optimistes la ou necessaire

### Iteration 33.8 — Endpoints Manquants (Use Cases 33.1-33.10) + Review Final
**Effort** : ~3h
**Contenu** :
- **CRITIQUE** : `POST /auth/forgot-password` + `POST /auth/reset-password`
- Harmoniser les doublons notifications (rh.php vs dashboard.php)
- Ajouter `GET /me/dashboard` (resume self-service employe)
- Review final : verifier que 250/250 routes sont dans openapi.yaml
- Mettre a jour FRONTEND_API_CONTRACT_MATRIX.md
- Mettre a jour CHANGELOG.md

---

## 5. Matrice de Priorite

| Iteration | Priorite | Justification |
|---|---|---|
| 33.1 | **CRITIQUE** | Core RH = usage quotidien, doit etre documente en priorite |
| 33.2 | **CRITIQUE** | Payroll = donnees sensibles, erreur = risque legal |
| 33.5 | **CRITIQUE** | Validation inline = vulnerabilite, maintenance penible |
| 33.8 | **CRITIQUE** | Forgot password = bloquant pour tout utilisateur en production |
| 33.3 | **HAUTE** | Modules etendus largement utilises (recrutement, formation, cabinet) |
| 33.6 | **HAUTE** | Sans authorize(), un employe pourrait acceder aux donnees d'un autre |
| 33.7 | **HAUTE** | Sans transaction, un crash mid-payroll laisse des donnees corrompues |
| 33.4 | **MOYENNE** | Admin/integrations = usage moins frequent |

---

## 6. Criteres de Completude

A la fin du Plan 33, l'API pourra etre declaree **production-ready** si :

1. **Documentation** : 250/250 routes documentees dans `openapi.yaml` avec schemas request/response
2. **Validation** : 0 `$request->validate()` inline dans les controllers
3. **Autorisation** : `$this->authorize()` ou Policy Gate dans 100% des controllers CRUD
4. **Integrite** : `DB::transaction` sur toutes les operations multi-table
5. **Self-service** : `POST /auth/forgot-password` + `POST /auth/reset-password` fonctionnels
6. **Matrice** : `FRONTEND_API_CONTRACT_MATRIX.md` mise a jour avec toutes les routes
7. **OpenAPI valide** : `openapi.yaml` passe la validation `spectral lint` sans erreur

---

## 7. Estimation Totale

| Metrique | Valeur |
|---|---|
| Iterations | 8 |
| Effort total estime | **~25h** |
| Fichiers impactes | ~120 (controllers + FormRequests + Policies + openapi.yaml) |
| Nouvelles classes | ~60 FormRequests + ~10 Policies + 2-3 controllers |
| Lignes OpenAPI ajoutees | ~4 000+ |

---

*Fin du Plan 33*
