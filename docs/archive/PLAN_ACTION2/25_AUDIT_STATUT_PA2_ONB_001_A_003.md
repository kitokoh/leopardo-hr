# Audit de statut — PA2-ONB-001, PA2-ONB-002, PA2-ONB-003 (issues #960, #961, #962)

**Method:** direct source inspection of the API modules, `front/web`, `front/admin-dashboard`, and the `leopardo_manager`/`leopardo_platform_admin` Flutter apps, cross-referenced against each ticket's acceptance criteria in `03_GITHUB_PROJECT_IMPORT.csv` / `02_BACKLOG_ATOMIQUE.md`.

---

## PA2-ONB-001 — Trial self-service de bout en bout (issue #960)

**Acceptance criteria:** *"Entreprise manager plan trial email welcome et retour credentials ou next-step"* — company + manager + trial plan created, welcome email sent, credentials or next-step returned.

`api/app/Modules/Billing/Interfaces/Api/V1/SelfServiceTrialController.php` implements the full flow end to end:

- `POST /api/v1/trial/signup` validates the lead, rejects an already-registered manager email (409 with a `login_url` next-step), generates a 6-digit OTP, persists a pending `CompanyRequest` (30-minute expiry), and emails the OTP via `TrialVerificationMail`.
- `POST /api/v1/trial/verify` validates the OTP, provisions the tenant (`Company` + principal `Employee` manager, country-aware language/currency/timezone via `CountryDefaults`, a trial plan resolved or auto-created as fallback), fires `CompanyCreated` for partner/growth attribution, sends `TrialWelcomeMail` with the generated temporary password, schedules J+1/J+3/J+7 drip emails (`SendTrialDripEmailJob`), and returns the company, manager credentials, trial end date, and explicit `next_steps` (login, change password, add employees) in the response.
- Both endpoints are exercised by `api/tests/Feature/SelfServiceTrialTest.php` (OTP send + pending request, successful verify + provisioning, invalid OTP rejection, duplicate-email rejection, field validation) — 5 tests covering every branch described above.
- The frontend consumes this via `front/web/src/app/api/forms/signup/route.ts` and `.../verify/route.ts`, with an honest "pending / contact under 24h" fallback state already handled (`PA2-MKT-002`, `SignupForm.tsx`) instead of a fake OTP screen when the backend is cold-starting.

**Finding: fully implemented, no gap.**

---

## PA2-ONB-002 — Activation client platform admin (issue #961)

**Acceptance criteria:** *"Creer voir activer client; pays devise langue timezone; tests contrat"* — create/view/activate a client company; country-aware currency/language/timezone; contract tests.

- **Create:** `App\Modules\Platform\Infrastructure\Services\CompanyProvisioningService` (used by both the platform-admin web and the `leopardo_platform_admin` mobile app's minimal-payload flow) creates the company + principal manager with country defaults resolved via `CountryDefaults`.
- **View:** `front/admin-dashboard/src/views/companies/{CompaniesView,CompanyDetailView}.vue` list and inspect a company's health, adoption metrics, module toggles, and subscription/support state.
- **Activate:** `CompanyDetailView.vue`'s `activateClient()` (bound to `#btn-activer-client`, shown only while `health.company.status === 'trial'`) flips the commercial status from `trial` to `active` and persists the subscription form.
- **Country/currency/language/timezone:** `CountryDefaults::for($country)` drives `language`/`currency`/`timezone` at company-creation time in both the provisioning service and the self-service trial flow above; `api/tests/Feature/PlatformCompanyProvisioningTest.php::test_super_admin_can_read_country_defaults_for_mobile_forms` and `::test_company_creation_seeds_default_schedule_from_country_rules` assert this explicitly.
- **Contract tests:** `api/tests/Feature/PlatformCompanyProvisioningTest.php` covers super-admin creation (full payload), platform-mobile creation (minimal payload), country-defaults lookup, and default-schedule seeding — 4 tests plus the super-admin login contract test in the same file. The `leopardo_platform_admin` mobile app has its own model/screen coverage (`company_create_screen.dart`, `company_detail_screen.dart`, `company_requests_screen.dart`, `test/models/platform_company_model_test.dart`).

**Finding: fully implemented, no gap.**

---

## PA2-ONB-003 — Onboarding wizard manager (issue #962)

**Acceptance criteria:** *"Premiere connexion guide horaires employes branding regles kiosk"* — first-login guided flow covering schedules, employees, branding, rules, kiosk.

- **`front/web`:** `modules/onboarding/components/OnboardingWizard.tsx`, mounted from `(dashboard)/layout.tsx` on first login, walks the manager through company/branding setup, inviting the team, and finalising schedules/company rules, persisting completion to `company.metadata.onboarding_completed` via `PATCH /company/branding`.
- **Mobile manager:** `leopardo_manager/lib/features/onboarding/screens/onboarding_screen.dart` renders a fuller, checklist-based guided wizard with a progress hero card and one card per step — `company_info` (branding), `first_department`, `first_employee` (employees), `first_attendance`, `invite_manager`, `configure_schedules` (horaires), `first_report`, `configure_payroll` (regles), `install_kiosk` (kiosk), `activate_geofence` — each markable complete or skippable (when not required), backed by `onboarding_provider.dart`/`onboarding_repository.dart`.
- **API:** `App\Modules\Onboarding` (`OnboardingController`, `OnboardingStepController`, `OnboardingChecklistController`, `OnboardingRepositoryInterface`) persists per-company step progress (`onboarding_progresses` table, migrated 2026-07-12) that both clients read from/write to.
- **Tests:** `api/tests/Feature/{OnboardingChecklistTest,OnboardingE2ETest,OnboardingStepControllerTest,Onboarding/OnboardingControllerTest,CompanyOnboardingIntegrationTest,EmployeeInvitationOnboardingTest}.php` on the backend, plus `leopardo_manager/test/models/onboarding_step_test.dart` on mobile — this is one of the most heavily tested modules in the codebase, not a stub.

**Finding: fully implemented, no gap.** Every acceptance-criteria keyword (horaires/`configure_schedules`, employes/`first_employee`, branding/`company_info`, regles/`configure_payroll`, kiosk/`install_kiosk`) maps to a concrete, working, tested checklist step.

---

## Decision

All three tickets are functionally complete. No application code changes were required for this audit — it is a closing/bookkeeping pass, consistent with the pattern already used for `PA2-JOB-004` (`17_AUDIT_STATUT_PA2_JOB_001_A_006.md`), `PA2-KIO-001` (`22_AUDIT_STATUT_PA2_KIO_001.md`), and `PA2-MOB-010` (`24_AUDIT_STATUT_PA2_MOB_010.md`).

**Recommendation:** close issues #960, #961, #962 referencing this document.
