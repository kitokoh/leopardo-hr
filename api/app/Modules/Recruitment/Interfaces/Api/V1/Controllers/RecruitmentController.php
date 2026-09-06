<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Interfaces\Api\V1\Controllers;

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
use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Recruitment\Domain\Models\Interview;
use App\Modules\Recruitment\Domain\Models\JobPosting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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

        return JobPostingResource::collection($query->orderByDesc('created_at')->paginate(max(1, min(100, $request->integer('per_page', 15)))))
            ->response();
    }

    public function storeJob(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if (! $actor->hasManagerRole('principal', 'rh')) {
            abort(403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:200',
            'description' => 'nullable|string',
            'department_id' => [
                'nullable',
                'integer',
                Rule::exists('departments', 'id')->where('company_id', $actor->company_id),
            ],
            'position_id' => [
                'nullable',
                'integer',
                Rule::exists('positions', 'id')->where('company_id', $actor->company_id),
            ],
            'location' => 'nullable|string|max:200',
            'remote_policy' => 'nullable|in:onsite,hybrid,remote',
            'contract_type' => 'nullable|in:cdi,cdd,stage,freelance',
            'salary_range_min' => 'nullable|numeric|min:0',
            'salary_range_max' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|max:3',
            'skills_required' => 'nullable|array',
            'closes_at' => 'nullable|date',
        ]);

        $job = app(CreateJobPostingAction::class)->execute(
            $actor->company_id,
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

    public function updateJob(Request $request, JobPosting $jobPosting): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($jobPosting->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->hasManagerRole('principal', 'rh')) {
            abort(403);
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:200',
            'description' => 'nullable|string',
            'department_id' => [
                'nullable',
                'integer',
                Rule::exists('departments', 'id')->where('company_id', $actor->company_id),
            ],
            'position_id' => [
                'nullable',
                'integer',
                Rule::exists('positions', 'id')->where('company_id', $actor->company_id),
            ],
            'location' => 'nullable|string|max:200',
            'remote_policy' => 'nullable|in:onsite,hybrid,remote',
            'contract_type' => 'nullable|in:cdi,cdd,stage,freelance',
            'salary_range_min' => 'nullable|numeric|min:0',
            'salary_range_max' => 'nullable|numeric|min:0',
            'skills_required' => 'nullable|array',
            'status' => 'sometimes|in:draft,published,closed,archived',
            'closes_at' => 'nullable|date',
        ]);

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

    public function storeApplicant(Request $request, JobPosting $jobPosting): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($jobPosting->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->hasManagerRole('principal', 'rh')) {
            abort(403);
        }

        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:30',
            'source' => 'nullable|in:website,referral,linkedin,agency,other',
            'cover_letter' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        // Issue #3860 — pas de doublon (job_posting_id, email), même garde
        // que le portail public : un doublon retourne 409 ALREADY_APPLIED.
        try {
            $applicant = app(CreateApplicantAction::class)->execute(
                $actor->company_id,
                $jobPosting,
                $validated,
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

    public function updateApplicant(Request $request, Applicant $applicant): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($applicant->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->hasManagerRole('principal', 'rh')) {
            abort(403);
        }

        $validated = $request->validate([
            'status' => 'sometimes|in:new,screening,interview,offer,hired,rejected,withdrawn',
            'rating' => 'nullable|integer|min:1|max:5',
            'notes' => 'nullable|string',
        ]);

        $applicant = app(UpdateApplicantAction::class)->execute($applicant, $validated);

        return (new ApplicantResource($applicant))->response();
    }

    // ── Interviews ──────────────────────────────────────────────────────────

    public function storeInterview(Request $request, Applicant $applicant): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($applicant->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->isManager()) {
            abort(403);
        }

        $validated = $request->validate([
            'interviewer_id' => [
                'nullable',
                'integer',
                Rule::exists('employees', 'id')->where('company_id', $actor->company_id),
            ],
            'type' => 'required|in:phone,video,onsite,technical',
            'scheduled_at' => 'required|date',
            'duration_minutes' => 'nullable|integer|min:15|max:480',
        ]);

        $interview = app(ScheduleInterviewAction::class)->execute(
            $actor->company_id,
            $applicant,
            $validated,
        );

        return (new InterviewResource($interview))
            ->response()
            ->setStatusCode(201);
    }

    public function updateInterview(Request $request, Interview $interview): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($interview->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->isManager()) {
            abort(403);
        }

        $validated = $request->validate([
            'status' => 'sometimes|in:scheduled,completed,cancelled,no_show',
            'feedback' => 'nullable|string',
            'rating' => 'nullable|integer|min:1|max:5',
        ]);

        $interview = app(UpdateInterviewAction::class)->execute($interview, $validated);

        return (new InterviewResource($interview))->response();
    }
}

