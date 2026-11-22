<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ApplicantResource;
use App\Http\Resources\Api\V1\InterviewResource;
use App\Http\Resources\Api\V1\JobPostingResource;
use App\Modules\Recruitment\Application\Actions\CreateApplicantAction;
use App\Modules\Recruitment\Application\Actions\CreateJobPostingAction;
use App\Modules\Recruitment\Application\Actions\ScheduleInterviewAction;
use App\Modules\Recruitment\Application\Actions\UpdateApplicantAction;
use App\Modules\Recruitment\Application\Actions\UpdateInterviewAction;
use App\Modules\Recruitment\Application\Actions\UpdateJobPostingAction;
use App\Modules\Recruitment\Domain\Exceptions\ApplicantAlreadyAppliedException;
use App\Modules\Recruitment\Domain\Models\Applicant;
use App\Modules\Recruitment\Domain\Models\Interview;
use App\Modules\Recruitment\Domain\Models\JobPosting;
use App\Modules\Recruitment\Infrastructure\Services\RecruitmentStatsService;
use App\Modules\Recruitment\Interfaces\Api\V1\Requests\RecruitmentStatsRequest;
use App\Modules\Recruitment\Interfaces\Api\V1\Requests\StoreRecruitmentApplicantRequest;
use App\Modules\Recruitment\Interfaces\Api\V1\Requests\StoreRecruitmentInterviewRequest;
use App\Modules\Recruitment\Interfaces\Api\V1\Requests\StoreRecruitmentJobRequest;
use App\Modules\Recruitment\Interfaces\Api\V1\Requests\UpdateRecruitmentApplicantRequest;
use App\Modules\Recruitment\Interfaces\Api\V1\Requests\UpdateRecruitmentInterviewRequest;
use App\Modules\Recruitment\Interfaces\Api\V1\Requests\UpdateRecruitmentJobRequest;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecruitmentController extends Controller
{
    // ── Statistics ──────────────────────────────────────────────────────────

    /**
     * GET /recruitment/stats — KPIs de recrutement pour dashboard RH (#9546).
     *
     * Agrège : nombre d'offres actives, candidatures par étape, taux de
     * conversion funnel, durée moyenne de recrutement, top sources.
     *
     * Permission : `recruitment.view` (Gate), scope tenant `company_id`.
     */
    public function stats(RecruitmentStatsRequest $request, RecruitmentStatsService $statsService): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('recruitment.view')) {
            abort(403);
        }

        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        $from = isset($validated['from']) ? Carbon::parse($validated['from'])->startOfDay() : null;
        $to = isset($validated['to']) ? Carbon::parse($validated['to'])->endOfDay() : null;

        return new JsonResponse([
            'data' => $statsService->overview((string) $actor->company_id, $from, $to),
        ]);
    }

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

        return JobPostingResource::collection($query->orderByDesc('created_at')->paginate(max(1, min(100, $request->integer('per_page', 15)))))
            ->response();
    }

    public function storeJob(StoreRecruitmentJobRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if (! $actor->hasManagerRole('principal', 'rh')) {
            abort(403);
        }

        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        $job = app(CreateJobPostingAction::class)->execute(
            (int) $actor->company_id,
            $actor->id,
            $validated,
        );

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

    public function updateJob(UpdateRecruitmentJobRequest $request, JobPosting $jobPosting): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($jobPosting->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->hasManagerRole('principal', 'rh')) {
            abort(403);
        }

        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        $jobPosting = app(UpdateJobPostingAction::class)->execute($jobPosting, $validated);

        return (new JobPostingResource($jobPosting))->response();
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

        return ApplicantResource::collection($query->orderByDesc('applied_at')->paginate(max(1, min(100, $request->integer('per_page', 15)))))
            ->response();
    }

    public function storeApplicant(StoreRecruitmentApplicantRequest $request, JobPosting $jobPosting): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($jobPosting->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->hasManagerRole('principal', 'rh')) {
            abort(403);
        }

        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        // Issue #3860 — pas de doublon (job_posting_id, email), même garde
        // que le portail public : un doublon retourne 409 ALREADY_APPLIED.
        try {
            $applicant = app(CreateApplicantAction::class)->execute(
                (string) $actor->company_id,
                $jobPosting,
                $validated,
                ['id' => $actor->id, 'name' => trim($actor->first_name.' '.$actor->last_name)],
            );
        } catch (ApplicantAlreadyAppliedException) {
            return new JsonResponse([
                'error' => 'ALREADY_APPLIED',
                'message' => 'A candidate with this email has already applied for this position.',
            ], 409);
        }

        return (new ApplicantResource($applicant))
            ->response()
            ->setStatusCode(201);
    }

    public function updateApplicant(UpdateRecruitmentApplicantRequest $request, Applicant $applicant): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($applicant->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->hasManagerRole('principal', 'rh')) {
            abort(403);
        }

        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        $applicant = app(UpdateApplicantAction::class)->execute(
            $applicant,
            $validated,
            ['id' => $actor->id, 'name' => trim($actor->first_name.' '.$actor->last_name)],
        );

        return (new ApplicantResource($applicant))->response();
    }

    // ── Interviews ──────────────────────────────────────────────────────────

    public function storeInterview(StoreRecruitmentInterviewRequest $request, Applicant $applicant): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($applicant->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->isManager()) {
            abort(403);
        }

        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        $interview = app(ScheduleInterviewAction::class)->execute(
            (int) $actor->company_id,
            $applicant,
            $validated,
        );

        return (new InterviewResource($interview))
            ->response()
            ->setStatusCode(201);
    }

    public function updateInterview(UpdateRecruitmentInterviewRequest $request, Interview $interview): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($interview->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->isManager()) {
            abort(403);
        }

        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        $interview = app(UpdateInterviewAction::class)->execute($interview, $validated);

        return (new InterviewResource($interview))->response();
    }
}
