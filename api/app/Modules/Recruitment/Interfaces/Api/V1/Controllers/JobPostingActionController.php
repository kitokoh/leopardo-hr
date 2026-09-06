<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ApplicantResource;
use App\Http\Resources\Api\V1\InterviewResource;
use App\Http\Resources\Api\V1\JobPostingResource;
use App\Modules\Recruitment\Application\Actions\CancelInterviewAction;
use App\Modules\Recruitment\Application\Actions\CloseJobPostingAction;
use App\Modules\Recruitment\Application\Actions\DeleteApplicantAction;
use App\Modules\Recruitment\Application\Actions\DeleteJobPostingAction;
use App\Modules\Recruitment\Application\Actions\PublishJobPostingAction;
use App\Modules\Recruitment\Application\Actions\SubmitInterviewFeedbackAction;
use App\Modules\Recruitment\Application\Actions\UpdateApplicantAction;
use App\Modules\Recruitment\Domain\Exceptions\JobPostingStateTransitionException;
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

        try {
            $job = app(PublishJobPostingAction::class)->execute($job);
        } catch (JobPostingStateTransitionException $e) {
            return response()->json(['message' => __('errors.'.$e->translationKey)], 422);
        }

        return (new JobPostingResource($job))->response();
    }

    public function close(Request $request, int $jobPosting): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        if (! $user->hasManagerRole('principal', 'rh')) {
            abort(403);
        }

        $job = JobPosting::where('company_id', $user->company_id)->findOrFail($jobPosting);

        try {
            $job = app(CloseJobPostingAction::class)->execute($job);
        } catch (JobPostingStateTransitionException $e) {
            return response()->json(['message' => __('errors.'.$e->translationKey)], 422);
        }

        return (new JobPostingResource($job))->response();
    }

    public function destroy(Request $request, int $jobPosting): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        if (! $user->hasManagerRole('principal', 'rh')) {
            abort(403);
        }

        $job = JobPosting::where('company_id', $user->company_id)->findOrFail($jobPosting);

        try {
            app(DeleteJobPostingAction::class)->execute($job);
        } catch (JobPostingStateTransitionException $e) {
            return response()->json(['message' => __('errors.'.$e->translationKey)], 422);
        }

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
        $applicant = app(UpdateApplicantAction::class)->execute($applicant, $validated);

        return (new ApplicantResource($applicant))->response();
    }

    public function destroyApplicant(Request $request, int $applicant): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        if (! $user->hasManagerRole('principal', 'rh')) {
            abort(403);
        }

        $applicant = Applicant::where('company_id', $user->company_id)->findOrFail($applicant);
        app(DeleteApplicantAction::class)->execute($applicant);

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

        $interview = app(SubmitInterviewFeedbackAction::class)->execute($interview, $validated);

        return (new InterviewResource($interview))->response();
    }

    public function destroyInterview(Request $request, int $interview): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        if (! $user->hasManagerRole('principal', 'rh')) {
            abort(403);
        }

        $interview = Interview::where('company_id', $user->company_id)->findOrFail($interview);
        app(CancelInterviewAction::class)->execute($interview);

        return response()->json(['message' => 'Interview cancelled.']);
    }
}
