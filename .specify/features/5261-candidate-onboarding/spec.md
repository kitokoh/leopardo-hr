# Feature Specification: Parcours candidat → employé tracé (issue #5261)

**Feature Branch**: `mod/hr/5261-candidate-onboarding`

**Created**: 2026-08-24

**Status**: Implemented

**Module**: `hr` — périmètre strict `api/app/Modules/HR/**` + migration
additive + event + routes dédiées. Aucun fichier des PRs en vol
(`EmployeeService.php`/`EmployeeController.php`/`rh.php`/`hr_extended.php`
verrouillés par #5331/#5345/#5323/#5303/#5314) n'est modifié.

## Contexte

Issue #5261 — fermer la boucle d'intégration : un candidat recruté devient
employé avec dossier complet, onboarding réalisé, puis éligible à la paie.
DoD : « Un recrutement simulé aboutit à un bulletin de paie ».

Modules existants : `Recruitment` (Applicant, JobPosting), `Onboarding`
(steps), HR (`EmployeeService::create` + `CreateEmployeeDTO`). Le gap = la
conversion tracée candidat → employé + la garde onboarding + la preuve paie.

## Décisions

1. **Migration additive** `candidate_id` nullable sur `employees` (index) —
   traçabilité candidat → employé, zéro backfill.
2. **`CandidateHiringService`** (HR, nouveau) : lit l'Applicant (Recruitment,
   **lecture seule** — périmètre HR strict), vérifie la garde onboarding
   (`OnboardingStep` obligatoires ≠ completed → 409) et l'anti-doublon
   (`status=hired` ou `candidate_id` existant → 422), puis crée l'Employee
   via `EmployeeService::create()` (service EXISTANT, non modifié) dans une
   transaction avec l'événement `CandidateHired`.
3. **`contract_start` posé** (défaut aujourd'hui dans EmployeeService) →
   « premier run de paie possible dès l'embauche datée » : le
   `PayrollCalculator` proratise déjà sur cette date.
4. **Endpoint** `POST /api/v1/hr/candidates/{applicant}/hire` (principal/RH)
   — routes dans un fichier dédié chargé par `HRServiceProvider` (rh.php /
   hr_extended.php verrouillés).
5. **Le statut de l'Applicant n'est PAS modifié** (responsabilité du module
   Recruitment) — la traçabilité vit côté employé (`candidate_id`).

## User Scenarios & Testing

### US1 — Un candidat devient employé (DoD)

**Independent Test**: `tests/Feature/HR/CandidateHiringTest.php` (5 tests,
21 assertions) — dont le DoD complet : applicant → hire → payroll-run
(calculate → validate) → **bulletin PDF réel (`%PDF`)**.

**Acceptance Scenarios**:

1. **Given** un candidat avec onboarding obligatoire complété, **When**
   `POST /api/v1/hr/candidates/{id}/hire`, **Then** 201 : Employee créé avec
   `candidate_id`, `contract_start` posé, `payroll_eligible=true`, événement
   `CandidateHired` émis.
2. **Given** un step onboarding obligatoire incomplet, **When** hire, **Then**
   409 et aucun Employee créé.
3. **Given** un candidat déjà embauché (statut `hired` ou candidate_id
   présent), **When** hire, **Then** 422 (anti-doublon, constitution §II).
4. **Given** un candidat inconnu, **When** hire, **Then** 404.
5. **Given** un candidat embauché, **When** un run de paie est créé pour la
   période, **Then** un bulletin est généré pour cet employé (DoD).

## Edge Cases

- `job_posting_id` NOT NULL en base → fixture avec JobPosting (enum
  `contract_type` en minuscules : `cdi`).
- Rôle paie : le manager du test doit être `principal` (403 sinon sur
  payroll-runs).
- `candidate_id` est une propriété dynamique (pas de @property sur Employee
  — fichier verrouillé par #5345) → lecture via `getAttribute()`.

## Deliverables

- [x] Spec `.specify/features/5261-candidate-onboarding/spec.md`
- [x] Migration `2026_08_24_000001_add_candidate_id_to_employees.php`
- [x] `CandidateHiringService` + `CandidateHired` event + 2 exceptions
- [x] `CandidateHiringController` + routes `candidate_hiring.php` (provider)
- [x] `CandidateHiringTest` (5 tests / 21 assertions — DoD bulletin réel)
- [x] CHANGELOG `[Unreleased]` + PR `Closes #5261`

## Validation

- Nouveaux tests : 5/5 ✅ · Suite HR : 68 tests / 271 assertions ✅
- PHPStan Strict : 0 erreur · Pint : PASS
