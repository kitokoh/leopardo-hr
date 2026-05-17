# RBAC Route Matrix

Date: 2026-05-14

This matrix maps the current API route surfaces to the roles allowed by the route middleware, controller policies, and feature gates. It is a security audit artifact: update it whenever a route group, middleware, policy, or monetized module changes.

## Role Legend

| Key | Role / guard | Scope |
|---|---|---|
| SA | `auth:super_admin_api` | Global platform administration only. |
| P | tenant manager with `manager_role=principal` | Full tenant administration, subject to company status and tenant middleware. |
| RH | tenant manager with `manager_role=rh` | HR lifecycle, employees, absences, documents, privacy operations. |
| DEPT | tenant manager with `manager_role=dept` | Department-scoped reads and approvals where controller policy allows it. |
| FIN | tenant manager with `manager_role=comptable` | Payroll, billing, bank exports, finance reads where policy allows it. |
| SUP | tenant manager with `manager_role=superviseur` | Team attendance, tasks, camera operations where policy allows it. |
| EMP | tenant employee | Self-service only unless a controller policy grants more. |
| PUBLIC | no authenticated user | Public onboarding, health, payment webhooks, kiosk/public token endpoints. |

## Global Route Guards

| Surface | Prefix / routes | Primary middleware | Allowed roles | Notes / evidence |
|---|---|---|---|---|
| Health and metrics | `/api/v1/health`, `/health/live`, `/health/ready`, `/metrics` | none, public probes | PUBLIC | Health probes are intentionally public for Render and monitors. |
| Tenant auth | `/api/v1/auth/*` login/register/google | `throttle:auth-sensitive` | PUBLIC | Login lockout and sensitive limiter are covered by auth/rate-limit tests. |
| Platform auth | `/api/v1/platform/auth/login` | `throttle:auth-sensitive` | PUBLIC | Creates `super_admin_api` session; 2FA may return `202 TWO_FA_REQUIRED`. |
| Public onboarding | `/api/v1/onboarding/invitation/*` | `throttle:10,1` | PUBLIC | Token-bound onboarding only. |
| Tenant authenticated base | `/api/v1/auth/me`, profile, privacy, features, company requests, onboarding checklist | `throttle:api`, `auth:sanctum`, `tenant` | P, RH, DEPT, FIN, SUP, EMP | `tenant` must resolve company and reject suspended/archived contexts before controller access. |
| Platform administration | `/api/v1/platform/*` except login | `auth:super_admin_api`, `throttle:platform-sensitive` | SA | Includes companies, plans, health, subscriptions, feature flags, company requests, metrics overview. |
| AI gateway | `/api/v1/ai/chat`, `/voice/*`, `/agent/*` | `auth:sanctum`, `tenant`, `AIFeatureCheck`, `AITenantInjector`, `AIRateLimiter`, `throttle:ai-sensitive` | P, RH, DEPT, FIN, SUP, EMP with AI feature | Voice and agent routes remain experimental and rate-limited. |
| AI analytics | `/api/v1/ai/analytics/*` | AI base middleware + `EnsureAIAnalyticsAccess` | P, RH | Security test should keep non-principal/non-RH managers and employees out. |
| Payment webhooks | `/api/v1/webhooks/stripe`, `/webhooks/chargily` | none route auth, controller signature validation | PUBLIC providers | Covered by webhook signature tests; unknown payloads must stay idempotent. |
| Kiosk device endpoints | `/api/v1/kiosks/{deviceCode}/roster|punch|sync` | `throttle:api`, device token in controller | PUBLIC device token | Device token is not a user role and must not bypass tenant scoping. |
| Camera public/internal endpoints | `/api/v1/internal/camera-token/verify`, `/api/v1/view/cam` | throttles + bearer/public token validation | PUBLIC token | No Sanctum session; access is token-bound. |

## Tenant Module Matrix

| Module / route family | P | RH | DEPT | FIN | SUP | EMP | Enforcement notes |
|---|:---:|:---:|:---:|:---:|:---:|:---:|---|
| Employees `/employees*` | RW | RW | R limited | R limited | R limited | self R | Employee policies and tenant filters must protect sensitive fields and cross-tenant reads. |
| Attendance `/attendance*` | RW | RW | R department | - | R team | self RW check-in/out | `Attendance*Test` covers self access, manager access, and 403 cases. |
| Attendance manager reports `/attendance/anomalies`, `/attendance/monthly-report` | R | R | R scoped | R finance | R team | - | Existing tests assert employee 403 for anomalies/monthly report. |
| Absences `/absences*` | RW approve/reject | RW approve/reject | approve scoped | - | approve scoped | create/self read/cancel | `Absence*Test` suites cover employee and manager paths. |
| Salary advances `/salary-advances*` | RW approve/reject | R workflow | - | RW disbursement/review | - | create/self read | `SalaryAdvanceSecurityTest` covers tenant isolation and forbidden access. |
| Payroll legacy `/payrolls*` | RW | R | - | RW | - | self where exposed | Use `throttle:payroll-sensitive`; payroll writes must remain policy-gated. |
| Payroll engine `/salary-*`, `/tax-slabs`, `/social-contributions`, `/payroll-runs`, `/bank-exports` | RW | R | - | RW | - | self payslips only | Self-service payslip routes are `/me/pay-slips*`; manager routes must not leak across tenant/FK chains. |
| HR referentials `/departments`, `/positions`, `/sites`, `/schedules` | RW | RW | R scoped | R | R scoped | - | Direct mutations should stay principal/RH unless policy explicitly broadens. |
| Notifications `/notifications*` | self | self | self | self | self | self | Notification resources must be actor-scoped. |
| Projects/tasks `/projects*`, `/tasks*` | RW | RW | RW scoped | - | RW team | assigned/self | Task comments are actor-scoped and tenant-scoped. |
| Evaluations `/evaluations*` | RW | RW | R scoped | - | R scoped | self acknowledge/read | `EvaluationSecurityTest` covers cross-tenant and forbidden actions. |
| Leave policies/balances `/leave-*`, `/me/leave-balances` | RW | RW | R scoped | - | R scoped | self R | `LeavePolicyApiTest` covers role and company scoping. |
| Contracts `/contracts*`, `/me/contracts` | RW | RW | R scoped | R | R scoped | self R | Contract document/PDF access must be policy-gated. |
| Recruitment `/recruitment*` | RW | RW | R scoped | - | R scoped | - | Candidate actions must validate employee/interviewer tenant. |
| Training `/training*`, `/me/trainings*` | RW | RW | R scoped | - | R scoped | enroll/self R | Self-enroll is limited to authenticated employee tenant. |
| Loans `/loans*`, `/me/loans*` | RW approve | R workflow | - | RW disburse | - | create/self R | Similar sensitivity to salary advances. |
| Expense claims `/expense-claims*` | RW approve/reject | R workflow | approve scoped | RW finance review | - | create/self submit | Expense items are FK-isolated; extend `FkChainTenantIsolationTest` when adding queries. |
| Org chart `/org-chart*` | R | R | R scoped | R | R team | self chain | Must not expose other tenant employee graph. |
| Reports `/reports*` | R | R | R scoped | R payroll/cost | R team | - | Reports must apply company and role scope before aggregates. |
| Tenant webhooks `/webhooks*` | RW | RW | - | - | - | - | Events/list/config are tenant admin surfaces; test schema includes webhook tables. |
| Audit logs `/audit-logs*` | R | R sensitive HR | - | R finance events | - | - | Logs are sensitive; future expansion should add explicit policy tests. |
| Approval workflows `/approval-*`, `/approvals*` | RW | RW | approve scoped | approve finance | approve team | requester only | Approval decisions are FK-isolated via request/workflow parent. |
| Billing `/billing*` | RW | - | - | RW | - | - | `BillingControllerTest` covers tenant isolation and employee denial. |
| Onboarding setup `/onboarding-setup*` | RW | RW | - | - | - | - | Distinct from public invitation and `/onboarding/checklist`. |
| Feature flag matrix `/feature-flags/matrix` | R only tenant view | R only tenant view | R only tenant view | R only tenant view | R only tenant view | R only tenant view | Matrix writes must remain platform-owned; `FeatureFlagControllerTest` guards tenant writes. |
| Dashboard `/dashboard/*` | R | R | R scoped | R finance | R team | self where exposed | Keep aggregates tenant-scoped. |
| Exports `/export/employees`, `/export/attendance` | R | R | R scoped | R finance | R team | - | Exports trigger sensitive data audit logs where HR data is included. |
| Cameras `/cameras*` | RW | R as policy allows | R scoped | - | R/RW team as policy allows | token/self none | Requires `module.cameras`; internal permissions are principal-only per route comments/controller policy. |
| User account linking `/user/*`, `/employees/link-user` | P/RH for linking | P/RH for linking | - | - | - | self account | `auth:user_api` and tenant linking must not cross company boundaries. |

## Required Test Evidence

| Security concern | Existing evidence | Gap to close next |
|---|---|---|
| Admin middleware does not allow every manager | `api/tests/Feature/Security/AdminMiddlewareRbacTest.php` | Add route-level regression if a new `admin` group appears. |
| AI analytics restricted to P/RH | `EnsureAIAnalyticsAccess` middleware and AI route group | Keep a focused Feature test for P/RH allowed and DEPT/SUP/EMP forbidden. |
| Cross-tenant model access | `TenantModelIsolationTest`, `CrossTenantValidationTest`, `IndexCrossTenantValidationTest`, `FkChainTenantIsolationTest` | Extend when adding models without direct `company_id`. |
| Payroll/billing sensitive data | `BillingControllerTest`, payroll integration tests, `SensitiveRateLimitTest` | Add role matrix tests for payroll engine manager roles. |
| Attendance manager scope | `AttendanceAnomaliesTest`, `AttendanceMonthlyReportTest`, attendance CRUD tests | Add department/supervisor positive-scope tests where policies mature. |

| Planning optimization endpoints | `PlanningOptimizationTest` | Scoped to `company_id` via tenant middleware; auth required. |

## Change Rule

Any PR adding or moving protected routes must update this matrix, the matching scenario registry, and at least one Feature/security test when the allowed role set changes.
