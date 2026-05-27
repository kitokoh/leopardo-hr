<?php

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
use App\Http\Requests\Api\V1\Recruitment\InterviewFeedbackJobPostingActionRequest;
use App\Http\Requests\Api\V1\Recruitment\UpdateApplicantStatusJobPostingActionRequest;

class JobPostingActionController extends Controller
{
    public function publish(Request $request, int $id): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        if (! $user->hasManagerRole('principal', 'rh')) {
            abort(403);
        }

        $job = JobPosting::where('company_id', $user->company_id)->findOrFail($id);

        if ($job->status !== 'draft') {
            return response()->json(['message' => 'Only draft postings can be published.'], 422);
        }

        $job->update([
            'status' => 'published',
            'published_at' => now(),
        ]);

        return (new JobPostingResource($job->fresh()))->response();
    }

    public function close(Request $request, int $id): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        if (! $user->hasManagerRole('principal', 'rh')) {
            abort(403);
        }

        $job = JobPosting::where('company_id', $user->company_id)->findOrFail($id);

        if ($job->status !== 'published') {
            return response()->json(['message' => 'Only published postings can be closed.'], 422);
        }

        $job->update(['status' => 'closed']);

        return (new JobPostingResource($job->fresh()))->response();
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        if (! $user->hasManagerRole('principal', 'rh')) {
            abort(403);
        }

        $job = JobPosting::where('company_id', $user->company_id)->findOrFail($id);

        if ($job->status !== 'draft') {
            return response()->json(['message' => 'Only draft postings can be deleted.'], 422);
        }

        $job->delete();

        return response()->json(['message' => 'Job posting deleted.']);
    }

    public function showApplicant(Request $request, int $id): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        if (! $user->isManager()) {
            abort(403);
        }

        $applicant = Applicant::where('company_id', $user->company_id)
            ->with(['jobPosting:id,title', 'interviews'])
            ->findOrFail($id);

        return (new ApplicantResource($applicant))->response();
    }

    public function updateApplicantStatus(UpdateApplicantStatusJobPostingActionRequest $request, int $id): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        if (! $user->hasManagerRole('principal', 'rh')) {
            abort(403);
        }

        $validated = $request->validated();

        $applicant = Applicant::where('company_id', $user->company_id)->findOrFail($id);
        $applicant->update($validated);

        return (new ApplicantResource($applicant->fresh()))->response();
    }

    public function destroyApplicant(Request $request, int $id): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        if (! $user->hasManagerRole('principal', 'rh')) {
            abort(403);
        }

        $applicant = Applicant::where('company_id', $user->company_id)->findOrFail($id);
        $applicant->delete();

        return response()->json(['message' => 'Applicant deleted.']);
    }

    public function interviewFeedback(InterviewFeedbackJobPostingActionRequest $request, int $id): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();

        $interview = Interview::where('company_id', $user->company_id)->findOrFail($id);

        $validated = $request->validated();

        $interview->update(array_merge($validated, ['status' => 'completed']));

        return (new InterviewResource($interview->fresh()))->response();
    }

    public function destroyInterview(Request $request, int $id): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        if (! $user->hasManagerRole('principal', 'rh')) {
            abort(403);
        }

        $interview = Interview::where('company_id', $user->company_id)->findOrFail($id);
        $interview->update(['status' => 'cancelled']);

        return response()->json(['message' => 'Interview cancelled.']);
    }
}
