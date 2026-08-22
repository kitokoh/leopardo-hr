<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Interfaces\Api\V1;

use App\Core\Auth\Domain\Models\User;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Http\Controllers\Controller;
use App\Modules\Recruitment\Domain\Models\Applicant;
use App\Modules\Recruitment\Domain\Models\JobPosting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class UserCandidateApplicationController extends Controller
{
    public function __construct(private readonly TenantManager $tenantManager) {}

    public function store(Request $request, string $companySlug, int $jobPosting): JsonResponse
    {
        /** @var User $user */
        $user = $request->user('user_api');
        $statuses = is_array($user->personal_statuses) ? $user->personal_statuses : [];
        if (! in_array('job_seeker', $statuses, true)) {
            return new JsonResponse([
                'error' => 'JOB_SEARCH_STATUS_REQUIRED',
                'message' => __('user.job_seeker_required'),
            ], 403);
        }

        $validated = $request->validate([
            'cover_letter' => ['nullable', 'string', 'max:5000'],
            'resume_url' => ['nullable', 'url', 'max:500'],
            'resume_id' => ['nullable', 'string', 'max:80'],
        ]);
        $company = Company::query()->where('slug', $companySlug)->where('status', 'active')->firstOrFail();

        return $this->tenantManager->withinTenant($company, function () use ($company, $jobPosting, $user, $validated): JsonResponse {
            $job = JobPosting::query()
                ->where('company_id', $company->id)
                ->published()
                ->where(function ($q): void {
                    $q->whereNull('closes_at')->orWhere('closes_at', '>=', now());
                })
                ->findOrFail($jobPosting);

            $alreadyApplied = Applicant::query()
                ->where('job_posting_id', $job->id)
                ->where(function ($q) use ($user): void {
                    $q->where('user_id', $user->id)->orWhere('email', $user->email);
                })
                ->exists();
            if ($alreadyApplied) {
                return new JsonResponse([
                    'error' => 'ALREADY_APPLIED',
                    'message' => __('user.already_applied'),
                ], 409);
            }

            $preferences = is_array($user->job_search_preferences) ? $user->job_search_preferences : [];
            $resumeUrl = $validated['resume_url'] ?? ($preferences['resume_url'] ?? null);
            if (! empty($validated['resume_id'])) {
                $selectedResume = collect(is_array($preferences['resumes'] ?? null) ? $preferences['resumes'] : [])
                    ->firstWhere('id', $validated['resume_id']);
                if (! is_array($selectedResume) || empty($selectedResume['path'])) {
                    return new JsonResponse(['error' => 'RESUME_NOT_FOUND', 'message' => __('user.resume_not_found')], 422);
                }
                $resumeUrl = $selectedResume['path'];
            }
            $applicant = Applicant::create([
                'company_id' => $company->id,
                'user_id' => $user->id,
                'job_posting_id' => $job->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'phone' => $user->phone,
                'resume_path' => $resumeUrl,
                'cover_letter' => $validated['cover_letter'] ?? null,
                'source' => 'website',
                'status' => 'new',
                'applied_at' => now(),
            ]);

            return new JsonResponse(['data' => [
                'id' => $applicant->id,
                'job_id' => $job->id,
                'company' => $company->name,
                'status' => $applicant->status,
                'resume_name' => $resumeUrl === ($preferences['resume_path'] ?? null) ? ($preferences['resume_name'] ?? null) : null,
                'applied_at' => $applicant->applied_at?->toIso8601String(),
            ]], 201);
        });
    }

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user('user_api');
        $applications = [];
        foreach (Company::query()->where('status', 'active')->get() as $company) {
            $tenantApplications = $this->tenantManager->withinTenant($company, function () use ($company, $user): array {
                return Applicant::query()
                    ->where('user_id', $user->id)
                    ->where('company_id', $company->id)
                    ->with(['jobPosting:id,title', 'statusHistory', 'interviews'])
                    ->select(['id', 'job_posting_id', 'company_id', 'status', 'applied_at', 'created_at'])
                    ->latest('applied_at')
                    ->limit(100)
                    ->get()
                    ->map(fn (Applicant $application): array => [
                        'id' => $application->id,
                        'job_posting_id' => $application->job_posting_id,
                        'company_id' => $application->company_id,
                        'status' => $application->status,
                        'resume_name' => $application->resume_path ? basename((string) $application->resume_path) : null,
                        'applied_at' => $application->applied_at?->toIso8601String(),
                        'created_at' => $application->created_at?->toIso8601String(),
                        'job' => ['id' => $application->jobPosting?->id, 'title' => $application->jobPosting?->title],
                        'interviews' => $application->interviews->map(fn ($interview) => [
                            'id' => $interview->id,
                            'type' => $interview->type,
                            'scheduled_at' => $interview->scheduled_at?->toIso8601String(),
                            'duration_minutes' => $interview->duration_minutes,
                            'status' => $interview->status,
                            'feedback' => $interview->feedback,
                        ])->values()->all(),
                        'status_history' => $application->statusHistory->map(fn ($event) => [
                            'id' => $event->id,
                            'from_status' => $event->from_status,
                            'to_status' => $event->to_status,
                            'note' => $event->note,
                            'actor_type' => $event->actor_type,
                            'changed_at' => $event->changed_at?->toIso8601String(),
                        ])->values()->all(),
                    ])
                    ->all();
            });
            $applications = [...$applications, ...$tenantApplications];
        }
        usort($applications, fn (array $a, array $b): int => strcmp((string) ($b['applied_at'] ?? ''), (string) ($a['applied_at'] ?? '')));
        return new JsonResponse(['data' => array_slice($applications, 0, 100)]);
    }
}
