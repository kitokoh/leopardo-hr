<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ApplicantResource;
use App\Http\Resources\Api\V1\InterviewResource;
use App\Http\Resources\Api\V1\JobPostingResource;
use App\Models\Applicant;
use App\Models\Employee;
use App\Models\Interview;
use App\Models\JobPosting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Http\Requests\Api\V1\Recruitment\StoreApplicantRecruitmentRequest;
use App\Http\Requests\Api\V1\Recruitment\StoreInterviewRecruitmentRequest;
use App\Http\Requests\Api\V1\Recruitment\StoreJobRecruitmentRequest;
use App\Http\Requests\Api\V1\Recruitment\UpdateApplicantRecruitmentRequest;
use App\Http\Requests\Api\V1\Recruitment\UpdateInterviewRecruitmentRequest;
use App\Http\Requests\Api\V1\Recruitment\UpdateJobRecruitmentRequest;

class RecruitmentController extends Controller
{
    // ── Job Postings ────────────────────────────────────────────────────────

    public function indexJobs(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if (! $actor->isManager()) {
            abort(403);
        }

        $query = JobPosting::query()
            ->where('company_id', $actor->company_id)
            ->with('department:id,name');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return JobPostingResource::collection($query->orderByDesc('created_at')->paginate($request->integer('per_page', 15)))
            ->response();
    }

    public function storeJob(StoreJobRecruitmentRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if (! $actor->hasManagerRole('principal', 'rh')) {
            abort(403);
        }

        $validated = $request->validated();

        $job = JobPosting::create([
            ...$validated,
            'company_id' => $actor->company_id,
            'created_by' => $actor->id,
            'status' => 'draft',
        ]);

        return (new JobPostingResource($job))
            ->response()
            ->setStatusCode(201);
    }

    public function showJob(Request $request, JobPosting $jobPosting): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($jobPosting->company_id !== $actor->company_id) {
            abort(404);
        }

        return (new JobPostingResource($jobPosting->load(['department:id,name', 'applicants'])))->response();
    }

    public function updateJob(UpdateJobRecruitmentRequest $request, JobPosting $jobPosting): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($jobPosting->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->hasManagerRole('principal', 'rh')) {
            abort(403);
        }

        $validated = $request->validated();

        if (isset($validated['status']) && $validated['status'] === 'published' && $jobPosting->status === 'draft') {
            $validated['published_at'] = now();
        }

        $jobPosting->update($validated);

        return (new JobPostingResource($jobPosting->fresh()))->response();
    }

    // ── Applicants ──────────────────────────────────────────────────────────

    public function indexApplicants(Request $request, JobPosting $jobPosting): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($jobPosting->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->isManager()) {
            abort(403);
        }

        $query = $jobPosting->applicants();

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return ApplicantResource::collection($query->orderByDesc('applied_at')->paginate($request->integer('per_page', 15)))
            ->response();
    }

    public function storeApplicant(StoreApplicantRecruitmentRequest $request, JobPosting $jobPosting): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($jobPosting->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->hasManagerRole('principal', 'rh')) {
            abort(403);
        }

        $validated = $request->validated();

        $applicant = Applicant::create([
            ...$validated,
            'company_id' => $actor->company_id,
            'job_posting_id' => $jobPosting->id,
        ]);

        return (new ApplicantResource($applicant))
            ->response()
            ->setStatusCode(201);
    }

    public function updateApplicant(UpdateApplicantRecruitmentRequest $request, Applicant $applicant): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($applicant->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->hasManagerRole('principal', 'rh')) {
            abort(403);
        }

        $validated = $request->validated();

        $applicant->update($validated);

        return (new ApplicantResource($applicant->fresh()))->response();
    }

    // ── Interviews ──────────────────────────────────────────────────────────

    public function storeInterview(StoreInterviewRecruitmentRequest $request, Applicant $applicant): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($applicant->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->isManager()) {
            abort(403);
        }

        $validated = $request->validated();

        $interview = Interview::create([
            ...$validated,
            'company_id' => $actor->company_id,
            'applicant_id' => $applicant->id,
        ]);

        return (new InterviewResource($interview))
            ->response()
            ->setStatusCode(201);
    }

    public function updateInterview(UpdateInterviewRecruitmentRequest $request, Interview $interview): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($interview->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->isManager()) {
            abort(403);
        }

        $validated = $request->validated();

        $interview->update($validated);

        return (new InterviewResource($interview->fresh()))->response();
    }
}
