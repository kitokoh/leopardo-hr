<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Employee;
use App\Http\Controllers\Controller;
use App\Models\Applicant;
use App\Models\Interview;
use App\Models\JobPosting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        return response()->json(['data' => $job->fresh()]);
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

        return response()->json(['data' => $job->fresh()]);
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

        return response()->json(['data' => $applicant]);
    }

    public function updateApplicantStatus(Request $request, int $id): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        if (! $user->hasManagerRole('principal', 'rh')) {
            abort(403);
        }

        $validated = $request->validate([
            'status' => 'required|in:new,screening,interview,offer,hired,rejected,withdrawn',
        ]);

        $applicant = Applicant::where('company_id', $user->company_id)->findOrFail($id);
        $applicant->update($validated);

        return response()->json(['data' => $applicant->fresh()]);
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

    public function interviewFeedback(Request $request, int $id): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();

        $interview = Interview::where('company_id', $user->company_id)->findOrFail($id);

        $validated = $request->validate([
            'feedback' => 'required|string|max:5000',
            'rating' => 'nullable|integer|min:1|max:5',
        ]);

        $interview->update(array_merge($validated, ['status' => 'completed']));

        return response()->json(['data' => $interview->fresh()]);
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
