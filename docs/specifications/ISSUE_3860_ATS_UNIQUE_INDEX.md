# ISSUE_3860 — ATS : pas d'index unique (job_posting_id, email)

**Statut**: Fixed (PR `fix/3860-ats-unique-index`) · **Priorité**: P2 · **Module**: api/ATS

## Constat

`applicants` (migration `2026_05_10_000005_create_recruitment_tables.php`)
n'avait que `index(['job_posting_id', 'status'])` → candidatures en double
illimitées pour le même poste + même email (double-clic, spam, retry, import).

## Correctif

1. Migration tenant additive `2026_08_15_000008_unique_applicant_per_job_email.php` :
   dédoublonnage préalable (garde la plus ancienne) puis index unique
   `(job_posting_id, email)` — job_posting_id identifie déjà le poste dans le
   schéma tenant.
2. `CandidateApplicationController::store` (public) et
   `RecruitmentController::storeApplicant` (manager) : check préalable + catch
   `QueryException 23505` → 409 `ALREADY_APPLIED` (pattern #3726).
3. Tests : doublon même poste → 409 ; même candidat sur 2 postes → 201×2.
