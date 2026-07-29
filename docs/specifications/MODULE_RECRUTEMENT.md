# Module Recrutement (ATS — Applicant Tracking System)

> ⚠️ **Spécification rétroactive (rédigée le 2026-07-29, module déjà en production).**
> `AGENTS.md` (« RÈGLE D'OR POUR LES NOUVEAUX MODULES ») exige explicitement une spec dans
> `docs/specifications/MODULE_RECRUTEMENT.md` — cité comme exemple littéral de la règle —
> **avant** de commencer à coder un nouveau module. Le module Recrutement/ATS a été mergé
> (issues #1324 « feat(recruitment): public ATS backend APIs », #1326 « ATS Admin: recruitment
> Kanban ») les 2026-07-27/28 sans qu'une telle spec n'existe (audit doc du 2026-07-29 :
> `git log --diff-filter=A` sur tout l'historique confirme qu'aucun fichier de ce nom n'a
> jamais été ajouté au dépôt). Ce document décrit donc le module **tel qu'il existe réellement
> dans le code à la date de rédaction**, à des fins de traçabilité et pour servir de référence
> aux futures évolutions — il ne précède pas l'implémentation, il la documente a posteriori.

## 1. Objet et périmètre

Le module Recrutement gère le cycle de vie complet d'une offre d'emploi et de ses candidatures,
pour le compte d'une entreprise cliente (tenant) de Leopardo RH :

- création, publication et fermeture d'offres d'emploi (`JobPosting`) ;
- portail public de candidature (page carrières par entreprise, flux XML Google Jobs/Indeed) ;
- réception et suivi des candidatures (`Applicant`) via un pipeline Kanban par statut ;
- planification et retour des entretiens (`Interview`).

Rattachement à la cartographie applicative d'`AGENTS.md` : le suivi du recrutement est l'une
des responsabilités de l'application mobile **`leopardo_hr`** (« Suivi des employés,
présences/absences, tâches, et gestion du recrutement (ATS) ») ; côté web, la gestion se fait
depuis le portail admin unique (`front/admin-dashboard`, vue `RecruitmentView.vue`), avec RBAC
appliqué dynamiquement selon le rôle (voir section 4).

## 2. Modèle de données (`api/database/migrations/tenant/2026_05_10_000005_create_recruitment_tables.php`)

Trois tables tenant-scopées (schéma `company_id` partagé, cohérent avec le reste du produit) :

### `job_postings`
| Colonne | Type | Notes |
|---|---|---|
| `company_id` | uuid, indexé | multitenant |
| `title` | string(200) | |
| `description` | text, nullable | |
| `department_id` / `position_id` | FK nullable, `nullOnDelete` | |
| `location` | string(200), nullable | |
| `remote_policy` | enum `onsite` / `hybrid` / `remote` | défaut `onsite` |
| `contract_type` | enum `cdi` / `cdd` / `stage` / `freelance` | défaut `cdi` |
| `salary_range_min` / `salary_range_max` | decimal(12,2) | |
| `currency` | string(3) | défaut `DZD` |
| `skills_required` | jsonb, nullable | |
| `status` | enum `draft` / `published` / `closed` / `archived` | défaut `draft` |
| `published_at` / `closes_at` | timestamptz, nullable | |
| `created_by` | unsignedInteger, nullable | |

Index composé `(company_id, status)`.

### `applicants`
| Colonne | Type | Notes |
|---|---|---|
| `company_id` | uuid, indexé | multitenant |
| `job_posting_id` | FK `job_postings`, `cascadeOnDelete` | |
| `first_name` / `last_name` / `email` / `phone` | | `phone` nullable |
| `resume_path` | string(500), nullable | fichier uploadé ou URL |
| `cover_letter` | text, nullable | |
| `source` | enum `website` / `referral` / `linkedin` / `agency` / `other` | défaut `website` |
| `status` | enum `new` / `screening` / `interview` / `offer` / `hired` / `rejected` / `withdrawn` | défaut `new` |
| `rating` | unsignedSmallInteger, nullable | |
| `notes` | text, nullable | |
| `applied_at` | timestamptz | défaut `now()` |

Index composé `(job_posting_id, status)`.

### `interviews`
| Colonne | Type | Notes |
|---|---|---|
| `applicant_id` | FK `applicants`, `cascadeOnDelete` | |
| `company_id` | uuid, indexé | |
| `interviewer_id` | FK `employees`, `nullOnDelete` | |
| `type` | enum `phone` / `video` / `onsite` / `technical` | défaut `onsite` |
| `scheduled_at` | timestamptz | |
| `duration_minutes` | unsignedSmallInteger | défaut `60` |
| `status` | enum `scheduled` / `completed` / `cancelled` / `no_show` | défaut `scheduled` |
| `feedback` | text, nullable | |
| `rating` | unsignedSmallInteger, nullable | |

## 3. Architecture applicative (DDD, `api/app/Modules/Recruitment/`)

Conforme aux conventions `App\Modules\<NomModule>\*` de `CONVENTIONS.md` :

```
Application/Actions/     PostJob.php, ScheduleInterview.php
Application/DTOs/        CreateJobPostingDTO.php
Domain/Contracts/        JobPostingRepositoryInterface.php
Domain/Exceptions/       ApplicantNotFoundException.php, JobPostingNotFoundException.php
Domain/Models/           JobPosting.php, Applicant.php, Interview.php
Infrastructure/Services/ ApplicantPipelineReader.php, RecruitmentService.php
Interfaces/Api/V1/       RecruitmentController.php (CRUD authentifié)
                          JobPostingActionController.php (publish/close/destroy/status)
                          PublicCareerController.php (portail public)
                          CandidateApplicationController.php (candidature publique)
Providers/                RecruitmentServiceProvider.php
```

Autorisation : `App\Policies\RecruitmentPolicy` (hors du dossier module, convention du repo pour
les policies transverses aux rôles manager) — `viewJobs`/`createJob`/`updateJob`/`deleteJob`
vérifient `Employee::hasManagerRole('principal', 'rh')` selon l'action, `viewApplicants`/
`manageApplicant` acceptent tout manager (`isManager()`), `scheduleInterview` est réservé à
`principal`/`rh`.

## 4. Endpoints API

### Authentifiés (`api/routes/modules/hr_extended.php`, middleware `throttle:api, auth:sanctum, tenant, throttle:api-plan`)

| Méthode | Route | Controller::méthode |
|---|---|---|
| GET | `/v1/recruitment/jobs` | `RecruitmentController::indexJobs` |
| POST | `/v1/recruitment/jobs` | `RecruitmentController::storeJob` |
| GET | `/v1/recruitment/jobs/{jobPosting}` | `RecruitmentController::showJob` |
| PUT | `/v1/recruitment/jobs/{jobPosting}` | `RecruitmentController::updateJob` |
| GET | `/v1/recruitment/jobs/{jobPosting}/applicants` | `RecruitmentController::indexApplicants` |
| POST | `/v1/recruitment/jobs/{jobPosting}/applicants` | `RecruitmentController::storeApplicant` |
| PUT | `/v1/recruitment/applicants/{applicant}` | `RecruitmentController::updateApplicant` |
| POST | `/v1/recruitment/applicants/{applicant}/interviews` | `RecruitmentController::storeInterview` |
| PUT | `/v1/recruitment/interviews/{interview}` | `RecruitmentController::updateInterview` |
| POST | `/v1/recruitment/jobs/{id}/publish` | `JobPostingActionController::publish` |
| POST | `/v1/recruitment/jobs/{id}/close` | `JobPostingActionController::close` |
| DELETE | `/v1/recruitment/jobs/{id}` | `JobPostingActionController::destroy` |
| GET | `/v1/recruitment/applicants/{id}` | `JobPostingActionController::showApplicant` |
| PATCH | `/v1/recruitment/applicants/{id}/status` | `JobPostingActionController::updateApplicantStatus` |
| DELETE | `/v1/recruitment/applicants/{id}` | `JobPostingActionController::destroyApplicant` |
| PATCH | `/v1/recruitment/interviews/{id}/feedback` | `JobPostingActionController::interviewFeedback` |
| DELETE | `/v1/recruitment/interviews/{id}` | `JobPostingActionController::destroyInterview` |

> Rappel `AGENTS.md` (ligne 150) : la vue admin consomme ces chemins réels ; ne jamais revenir
> aux chemins historiques inexistants `/v1/job-postings` ou `/v1/applicants`.

### Publics/non authentifiés (`api/routes/api.php`, middleware `throttle:public-careers`, tenant résolu par `{companySlug}` et non par Sanctum)

| Méthode | Route | Controller::méthode |
|---|---|---|
| GET | `/api/v1/public/careers/{companySlug}` | `PublicCareerController::index` |
| GET | `/api/v1/public/careers/{companySlug}/feed.xml` | `PublicCareerController::feed` |
| GET | `/api/v1/public/careers/{companySlug}/jobs/{jobPosting}` | `PublicCareerController::show` |
| POST | `/api/v1/public/careers/{companySlug}/jobs/{jobPosting}/apply` | `CandidateApplicationController::store` |

Ces routes ne retournent/n'acceptent jamais de candidatures sur des offres `draft`/`closed`
(scope `JobPosting::published()`), et le flux XML (`feed.xml`) est mis en cache 15 minutes par
entreprise. Le rate-limiter dédié `public-careers` (IP-based) protège contre le scraping/spam
sur cette surface intentionnellement anonyme.

## 5. Surfaces front consommant le module

| Surface | Chemin | Rôle |
|---|---|---|
| Portail admin (Vue) | `front/admin-dashboard/src/views/recruitment/RecruitmentView.vue` + `src/components/recruitment/ApplicantDetailModal.vue` + `src/components/common/KanbanBoard.vue` (partagé) | Kanban drag & drop par statut candidat, publication/fermeture d'offre, création d'offre. E2E : `front/admin-dashboard/e2e/recruitment-flow.spec.js` |
| Portail carrières public (Next.js) | `front/web/src/app/[companySlug]/careers/` (+ `careers/jobs/[jobId]/`, `ApplyForm.tsx`) et `front/web/src/app/(landing)/careers/` | Liste/détail des offres publiées, formulaire de candidature, consomme `careers-api.ts` |
| Mobile `leopardo_hr` | (voir cartographie `AGENTS.md`) | Suivi du recrutement côté RH, sans duplication du portail public |

## 6. Tests

| Fichier | Portée |
|---|---|
| `api/tests/Feature/RecruitmentControllerTest.php` | CRUD authentifié offres/candidats/entretiens, RBAC |
| `api/tests/Feature/PublicCareerControllerTest.php` | Isolation tenant (une offre d'une entreprise ne fuite jamais vers une autre), visibilité draft/closed (404/absent de la liste), contenu du flux XML, soumission/validation de candidature |
| `front/admin-dashboard/e2e/recruitment-flow.spec.js` | Parcours Kanban drag & drop, publication/fermeture depuis l'UI |
| `front/web/src/lib/__tests__/careers-api.test.ts` | Client API du portail carrières public |

## 7. Écarts connus / hors périmètre actuel

- Pas de webhook sortant (ATS externe, LinkedIn Job Sync) — candidatures et offres restent
  internes à Leopardo RH.
- Pas de scoring automatique / matching IA des candidats (voir `docs/ai/README.md` pour la
  feuille de route IA générale du produit, non spécifique au recrutement à ce jour).
- `resume_path` accepte un fichier uploadé (stockage `local`, chemin `recruitment/{company_id}/resumes`)
  ou une URL externe (`resume_url`) côté `CandidateApplicationController` — pas de scan antivirus
  ni de limite de taille documentée au-delà des règles de validation par défaut de Laravel.

## 8. Traçabilité

- Migration : `api/database/migrations/tenant/2026_05_10_000005_create_recruitment_tables.php`
- Merges principaux : issue #1324 (backend public ATS), issue #1326 (Kanban admin), 2026-07-27/28
- Cette spec rétroactive : audit documentaire du 2026-07-29 (voir `CHANGELOG.md` et
  `docs/JOURNAL_RACINE.md` pour l'entrée correspondante)
