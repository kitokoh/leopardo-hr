<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Interfaces\Api\V1;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ApplicantResource;
use App\Http\Resources\Api\V1\InterviewResource;
use App\Http\Resources\Api\V1\JobPostingResource;
use App\Modules\Recruitment\Domain\Models\Applicant;
use App\Modules\Recruitment\Domain\Models\Interview;
use App\Modules\Recruitment\Domain\Models\JobPosting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JobPostingActionController extends Controller
{
    public function publish(Request $request, int $jobPosting): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        if (! $user->hasManagerRole('principal', 'rh')) {
            abort(403);
        }

        $job = JobPosting::where('company_id', $user->company_id)->findOrFail($jobPosting);

        if ($job->status !== 'draft') {
            return response()->json(['message' => __('errors.JOB_POSTING_DRAFT_ONLY_PUBLISH')], 422);
        }

        $job->update([
            'status' => 'published',
            'published_at' => now(),
        ]);

        return (new JobPostingResource($job->fresh()))->response();
    }

    public function close(Request $request, int $jobPosting): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        if (! $user->hasManagerRole('principal', 'rh')) {
            abort(403);
        }

        $job = JobPosting::where('company_id', $user->company_id)->findOrFail($jobPosting);

        if ($job->status !== 'published') {
            return response()->json(['message' => __('errors.JOB_POSTING_PUBLISHED_ONLY_CLOSE')], 422);
        }

        $job->update(['status' => 'closed']);

        return (new JobPostingResource($job->fresh()))->response();
    }

    public function destroy(Request $request, int $jobPosting): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        if (! $user->hasManagerRole('principal', 'rh')) {
            abort(403);
        }

        $job = JobPosting::where('company_id', $user->company_id)->findOrFail($jobPosting);

        if ($job->status !== 'draft') {
            return response()->json(['message' => __('errors.JOB_POSTING_DRAFT_ONLY_DELETE')], 422);
        }

        $job->delete();

        return response()->json(['message' => __('errors.JOB_POSTING_DELETED')]);
    }

    public function showApplicant(Request $request, int $applicant): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        if (! $user->isManager()) {
            abort(403);
        }

        $applicant = Applicant::where('company_id', $user->company_id)
            ->with(['jobPosting:id,title', 'interviews'])
            ->findOrFail($applicant);

        return (new ApplicantResource($applicant))->response();
    }

    public function updateApplicantStatus(Request $request, int $applicant): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        if (! $user->hasManagerRole('principal', 'rh')) {
            abort(403);
        }

        $validated = $request->validate([
            'status' => 'required|in:new,screening,interview,offer,hired,rejected,withdrawn',
        ]);

        $applicant = Applicant::where('company_id', $user->company_id)->findOrFail($applicant);
        $applicant->update($validated);

        return (new ApplicantResource($applicant->fresh()))->response();
    }

    public function destroyApplicant(Request $request, int $applicant): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        if (! $user->hasManagerRole('principal', 'rh')) {
            abort(403);
        }

        $applicant = Applicant::where('company_id', $user->company_id)->findOrFail($applicant);
        $applicant->delete();

        return response()->json(['message' => 'Applicant deleted.']);
    }

    public function interviewFeedback(Request $request, int $interview): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();

        $interview = Interview::where('company_id', $user->company_id)->findOrFail($interview);

        $validated = $request->validate([
            'feedback' => 'required|string|max:5000',
            'rating' => 'nullable|integer|min:1|max:5',
        ]);

        $interview->update(array_merge($validated, ['status' => 'completed']));

        return (new InterviewResource($interview->fresh()))->response();
    }

    public function destroyInterview(Request $request, int $interview): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        if (! $user->hasManagerRole('principal', 'rh')) {
            abort(403);
        }

        $interview = Interview::where('company_id', $user->company_id)->findOrFail($interview);
        $interview->update(['status' => 'cancelled']);

        return response()->json(['message' => 'Interview cancelled.']);
    }
}
