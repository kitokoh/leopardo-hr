<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Interfaces\Api\V1\Controllers;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ApplicantResource;
use App\Modules\Recruitment\Application\Actions\CreateApplicantAction;
use App\Modules\Recruitment\Application\Services\ResumeScanService;
use App\Modules\Recruitment\Domain\Contracts\ResumeTextExtraction;
use App\Modules\Recruitment\Domain\Exceptions\ApplicantAlreadyAppliedException;
use App\Modules\Recruitment\Domain\Exceptions\ResumeRejectedException;
use App\Modules\Recruitment\Domain\Models\JobPosting;
use App\Modules\Recruitment\Interfaces\Api\V1\Requests\PublicApplyRequest;
use Illuminate\Http\JsonResponse;

/**
 * CandidateApplicationController — Public (unauthenticated) endpoint used by
 * the careers portal for candidates to submit an application for a
 * published job posting. Creates the Applicant record and stores the
 * uploaded resume, if any.
 *
 * BC-10 (#9217) — le CV est analysé AVANT d'être rangé (cf. `store()`).
 */
class CandidateApplicationController extends Controller
{
    public function __construct(
        private readonly TenantManager $tenantManager,
        private readonly ResumeScanService $resumeScanService,
        private readonly ResumeTextExtraction $resumeTextExtraction,
    ) {}

    /**
     * POST /api/v1/public/careers/{companySlug}/jobs/{jobPosting}/apply
     */
    public function store(PublicApplyRequest $request, string $companySlug, int $jobPosting): JsonResponse
    {
        $company = $this->resolveCompany($companySlug);

        return $this->tenantManager->withinTenant($company, function () use ($request, $company, $jobPosting): JsonResponse {
            $job = JobPosting::query()
                ->where('company_id', $company->id)
                ->published()
                ->findOrFail($jobPosting);

            $validated = $request->validated();

            $resumePath = null;
            $scanVerdict = null;
            $resumeText = null;
            $resumeTextExtractedAt = null;

            if ($request->hasFile('resume')) {
                $file = $request->file('resume');

                // BC-10 (#9217) — analyse antivirus AVANT le stockage définitif.
                //
                // L'ordre compte : on analyse le fichier temporaire, et si la
                // politique le refuse, rien n'a encore été écrit dans le
                // répertoire du tenant. Analyser après `store()` obligerait à
                // supprimer un fichier déjà rangé — et laisserait une fenêtre
                // pendant laquelle un document malveillant serait à sa place
                // définitive, téléchargeable par un recruteur.
                try {
                    $scanVerdict = $this->resumeScanService->inspect(
                        $file->getRealPath(),
                        $file->getClientOriginalName(),
                    );
                } catch (ResumeRejectedException) {
                    // Le fichier temporaire est détruit par PHP en fin de
                    // requête : rien à nettoyer, rien n'a été persisté.
                    return new JsonResponse([
                        'error' => 'RESUME_REJECTED',
                        'message' => __('errors.RESUME_REJECTED'),
                        'errors' => [
                            'resume' => [__('errors.RESUME_REJECTED')],
                        ],
                    ], 422);
                }

                $resumePath = $file->store("recruitment/{$company->id}/resumes", 'local');

                // ATS (#9461) — extraction du TEXTE du CV, APRÈS le scan et
                // APRÈS le stockage : on lit le fichier à sa place définitive,
                // donc le texte correspond exactement au document que le
                // recruteur téléchargera. Un CV illisible (scan image, PDF
                // chiffré) rend `null` sans faire échouer la candidature.
                //
                // `store()` rend `string|false` : on teste `is_string` et non
                // `!== null`, sinon un échec d'écriture disque passerait pour
                // un succès et `path()` recevrait `false`.
                if (is_string($resumePath) && $resumePath !== '') {
                    $resumeText = $this->resumeTextExtraction->extract(
                        \Illuminate\Support\Facades\Storage::disk('local')->path($resumePath),
                        $file->getClientOriginalName(),
                    );
                    $resumeTextExtractedAt = $resumeText !== null ? now() : null;
                }
            }

            // BC-10 (#9100) — RGPD : on horodate le consentement et on fixe la
            // fin de conservation. La durée est configurable
            // (`recruitment.applicant_retention_days`, 730 j par défaut, soit
            // les 24 mois annoncés au candidat) : une durée codée en dur serait
            // impossible à ajuster par pays.
            $retentionDays = (int) config('recruitment.applicant_retention_days', 730);
            $consentAt = now();

            // Issue #3860 — pas de doublon (job_posting_id, email) : le
            // double-clic, le spam ou un retry ne doivent pas créer plusieurs
            // candidatures. Garde applicative dans l'Action + index unique en
            // base (le catch 23505 couvre la course check/create).
            try {
                $applicant = app(CreateApplicantAction::class)->execute(
                    (string) $company->id,
                    $job,
                    [
                        'first_name' => $validated['first_name'],
                        'last_name' => $validated['last_name'],
                        'email' => $validated['email'],
                        'phone' => $validated['phone'] ?? null,
                        'resume_path' => $resumePath ?? ($validated['resume_url'] ?? null),
                        // ATS (#9461) — texte extrait du CV, base du score de
                        // correspondance. `null` = non extrait (CV image ou
                        // chiffré), ce qui n'est pas la même chose que vide.
                        'resume_text' => $resumeText,
                        'resume_text_extracted_at' => $resumeTextExtractedAt,
                        // BC-10 (#9217) — le verdict est persisté, y compris
                        // `unavailable` : un CV non analysé doit rester
                        // distinguable d'un CV vérifié, et re-scannable après
                        // mise à jour de la base virale.
                        'resume_scan_status' => $scanVerdict?->status,
                        'resume_scan_engine' => $scanVerdict?->engine,
                        'resume_scan_signature' => $scanVerdict?->signature,
                        'resume_scanned_at' => $scanVerdict !== null ? $consentAt : null,
                        'cover_letter' => $validated['cover_letter'] ?? null,
                        'source' => $validated['source'] ?? 'website',
                        'status' => 'new',
                        'applied_at' => $consentAt,
                        'consent_given_at' => $consentAt,
                        'retention_until' => $consentAt->copy()->addDays($retentionDays),
                    ],
                );
            } catch (ApplicantAlreadyAppliedException) {
                return new JsonResponse([
                    'error' => 'ALREADY_APPLIED',
                    'message' => 'You have already applied for this position.',
                ], 409);
            }

            return (new ApplicantResource($applicant))
                ->response()
                ->setStatusCode(201);
        });
    }

    private function resolveCompany(string $companySlug): Company
    {
        return Company::query()
            ->where('slug', $companySlug)
            ->where('status', '!=', 'suspended')
            ->firstOrFail();
    }
}
