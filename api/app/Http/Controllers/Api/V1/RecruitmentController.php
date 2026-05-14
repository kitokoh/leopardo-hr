<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
use App\Models\Employee;
use App\Models\Interview;
use App\Models\JobPosting;
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

        return response()->json($query->orderByDesc('created_at')->paginate($request->integer('per_page', 15)));
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

        $job = JobPosting::create([
            ...$validated,
            'company_id' => $actor->company_id,
            'created_by' => $actor->id,
            'status' => 'draft',
        ]);

        return response()->json(['data' => $job], 201);
    }

    public function showJob(Request $request, JobPosting $jobPosting): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($jobPosting->company_id !== $actor->company_id) {
            abort(404);
        }

        return response()->json(['data' => $jobPosting->load(['department:id,name', 'applicants'])]);
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

        if (isset($validated['status']) && $validated['status'] === 'published' && $jobPosting->status === 'draft') {
            $validated['published_at'] = now();
        }

        $jobPosting->update($validated);

        return response()->json(['data' => $jobPosting->fresh()]);
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

        return response()->json($query->orderByDesc('applied_at')->paginate($request->integer('per_page', 15)));
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

        $applicant = Applicant::create([
            ...$validated,
            'company_id' => $actor->company_id,
            'job_posting_id' => $jobPosting->id,
        ]);

        return response()->json(['data' => $applicant], 201);
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

        $applicant->update($validated);

        return response()->json(['data' => $applicant->fresh()]);
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

        $interview = Interview::create([
            ...$validated,
            'company_id' => $actor->company_id,
            'applicant_id' => $applicant->id,
        ]);

        return response()->json(['data' => $interview], 201);
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

        $interview->update($validated);

        return response()->json(['data' => $interview->fresh()]);
    }
}
