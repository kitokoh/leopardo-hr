<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Interfaces\Api\V1;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ApplicantResource;
use App\Modules\Recruitment\Domain\Models\Applicant;
use App\Modules\Recruitment\Domain\Models\JobPosting;
use App\Modules\Recruitment\Interfaces\Api\V1\Requests\PublicApplyRequest;
use Illuminate\Http\JsonResponse;

/**
 * CandidateApplicationController — Public (unauthenticated) endpoint used by
 * the careers portal for candidates to submit an application for a
 * published job posting. Creates the Applicant record and stores the
 * uploaded resume, if any.
 */
class CandidateApplicationController extends Controller
{
    public function __construct(private readonly TenantManager $tenantManager) {}

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
            if ($request->hasFile('resume')) {
                $resumePath = $request->file('resume')->store("recruitment/{$company->id}/resumes", 'local');
            }

            $applicant = Applicant::create([
                'company_id' => $company->id,
                'job_posting_id' => $job->id,
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'resume_path' => $resumePath ?? ($validated['resume_url'] ?? null),
                'cover_letter' => $validated['cover_letter'] ?? null,
                'source' => $validated['source'] ?? 'website',
                'status' => 'new',
                'applied_at' => now(),
            ]);

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
