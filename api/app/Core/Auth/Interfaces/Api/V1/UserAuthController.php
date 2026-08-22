<?php

declare(strict_types=1);

namespace App\Core\Auth\Interfaces\Api\V1;

use App\Http\Controllers\Controller;
use App\Core\Auth\Domain\Models\User;
use App\Core\Auth\Infrastructure\Services\UserAuthService;
use App\Modules\Recruitment\Application\Services\JobRecommendationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rule;

class UserAuthController extends Controller
{
    public function __construct(
        private readonly UserAuthService $userAuthService,
        private readonly JobRecommendationService $jobRecommendationService,
    ) {}

    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', Password::min(8)],
            'phone' => ['nullable', 'string', 'max:20'],
        ]);

        $result = $this->userAuthService->register(
            firstName: $validated['first_name'],
            lastName: $validated['last_name'],
            email: $validated['email'],
            password: $validated['password'],
            phone: $validated['phone'] ?? null,
        );

        return new JsonResponse([
            'data' => $this->formatUser($result['user']),
            'token' => $result['token'],
            'token_type' => $result['token_type'],
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $result = $this->userAuthService->login(
            email: $validated['email'],
            password: $validated['password'],
            deviceName: $validated['device_name'] ?? null,
        );

        return new JsonResponse([
            'data' => $this->formatUser($result['user']),
            'token' => $result['token'],
            'token_type' => $result['token_type'],
        ]);
    }

    public function googleSignIn(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_token' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $result = $this->userAuthService->googleSignIn(
            idToken: $validated['id_token'],
            deviceName: $validated['device_name'] ?? null,
        );

        return new JsonResponse([
            'data' => $this->formatUser($result['user']),
            'token' => $result['token'],
            'token_type' => $result['token_type'],
            'is_new' => $result['is_new'],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user('user_api');

        return new JsonResponse([
            'data' => $this->formatUser($user),
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => ['sometimes', 'string', 'max:100'],
            'last_name' => ['sometimes', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20'],
            'preferred_language' => ['sometimes', 'string', 'size:2'],
        ]);

        /** @var User $user */
        $user = $request->user('user_api');
        $user->update($validated);

        /** @var User $fresh */
        $fresh = $user->fresh();

        return new JsonResponse([
            'data' => $this->formatUser($fresh),
        ]);
    }

    public function updatePersonalOnboarding(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'statuses' => ['required', 'array', 'min:1'],
            'statuses.*' => [
                'string',
                Rule::in(['student', 'employee', 'entrepreneur', 'job_seeker']),
            ],
        ]);

        /** @var User $user */
        $user = $request->user('user_api');
        $statuses = array_values(array_unique($validated['statuses']));
        $user->forceFill([
            'personal_statuses' => $statuses,
            'personal_onboarding_completed_at' => now(),
        ])->save();

        return new JsonResponse([
            'data' => $this->personalOnboardingPayload($user->fresh()),
        ]);
    }

    public function personalOnboarding(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user('user_api');

        return new JsonResponse([
            'data' => $this->personalOnboardingPayload($user),
        ]);
    }

    public function uploadResume(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'resume' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:5120'],
        ]);
        /** @var User $user */
        $user = $request->user('user_api');
        $preferences = is_array($user->job_search_preferences) ? $user->job_search_preferences : [];
        $path = $validated['resume']->store('user-resumes/'.$user->id, 'local');
        $resume = [
            'id' => (string) str()->uuid(),
            'path' => $path,
            'name' => $validated['resume']->getClientOriginalName(),
            'uploaded_at' => now()->toIso8601String(),
        ];
        $resumes = is_array($preferences['resumes'] ?? null) ? $preferences['resumes'] : [];
        $resumes[] = $resume;
        $preferences['resumes'] = array_slice($resumes, -10);
        $preferences['resume_id'] = $resume['id'];
        $preferences['resume_path'] = $path;
        $preferences['resume_url'] = $path;
        $preferences['resume_name'] = $resume['name'];
        $preferences['resume_uploaded_at'] = $resume['uploaded_at'];
        $user->forceFill([
            'job_search_preferences' => $preferences,
            'job_search_profile_updated_at' => now(),
        ])->save();
        return new JsonResponse(['data' => [
            'resume' => $resume,
            'resumes' => $preferences['resumes'],
            'selected_resume_id' => $preferences['resume_id'],
        ]], 201);
    }

    public function updateJobSearchProfile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'target_titles' => ['sometimes', 'array', 'max:20'],
            'target_titles.*' => ['string', 'max:120'],
            'skills' => ['sometimes', 'array', 'max:50'],
            'skills.*' => ['string', 'max:80'],
            'locations' => ['sometimes', 'array', 'max:20'],
            'locations.*' => ['string', 'max:120'],
            'contract_types' => ['sometimes', 'array', 'max:10'],
            'contract_types.*' => ['string', Rule::in(['cdi', 'cdd', 'stage', 'freelance'])],
            'remote_only' => ['sometimes', 'boolean'],
            'min_salary' => ['nullable', 'numeric', 'min:0'],
            'resume_url' => ['nullable', 'url', 'max:500'],
            'resume_id' => ['nullable', 'string', 'max:80'],
        ]);

        /** @var User $user */
        $user = $request->user('user_api');
        $user->forceFill([
            'job_search_preferences' => $validated,
            'job_search_profile_updated_at' => now(),
        ])->save();

        return new JsonResponse(['data' => [
            'preferences' => $user->fresh()->job_search_preferences ?? [],
            'updated_at' => $user->fresh()->job_search_profile_updated_at?->toIso8601String(),
        ]]);
    }

    public function jobRecommendations(Request $request): JsonResponse
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
            'q' => ['nullable', 'string', 'max:120'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);
        return new JsonResponse($this->jobRecommendationService->recommend(
            $user,
            $validated['q'] ?? null,
            (int) ($validated['limit'] ?? 20),
        ));
    }

    public function applicationNotifications(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user('user_api');
        $notifications = is_array($user->job_application_notifications) ? $user->job_application_notifications : [];
        return new JsonResponse(['data' => array_values(array_reverse($notifications))]);
    }

    public function markApplicationNotificationsRead(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user('user_api');
        $notifications = is_array($user->job_application_notifications) ? $user->job_application_notifications : [];
        $user->forceFill(['job_application_notifications' => array_map(
            fn (array $notification): array => [...$notification, 'read' => true],
            $notifications,
        )])->save();
        return new JsonResponse(['status' => 'ok']);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', Password::min(8), 'confirmed'],
        ]);

        /** @var User $user */
        $user = $request->user('user_api');

        if ($user->password_hash && ! Hash::check($validated['current_password'], $user->password_hash)) {
            return new JsonResponse([
                'error' => 'INVALID_CURRENT_PASSWORD',
                'message' => __('errors.INVALID_CURRENT_PASSWORD'),
            ], 422);
        }

        // #4695 : password_hash hors $fillable — assignation explicite.
        $user->forceFill(['password_hash' => Hash::make($validated['new_password'])])->save();

        return new JsonResponse(['status' => 'ok']);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user('user_api')?->currentAccessToken()?->delete();

        return new JsonResponse(['message' => 'LOGGED_OUT']);
    }

    /**
     * @return array<string, mixed>
     */
    private function personalOnboardingPayload(User $user): array
    {
        $statuses = is_array($user->personal_statuses) ? $user->personal_statuses : [];

        return [
            'statuses' => array_values($statuses),
            'completed' => $user->personal_onboarding_completed_at !== null,
            'employee_access' => [
                'linked' => $user->employeeLinks()->where('status', 'active')->exists(),
                'pointage_enabled' => $user->employeeLinks()->where('status', 'active')->exists(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatUser(?User $user): array
    {
        if (! $user) {
            return [];
        }

        return [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'avatar_url' => $user->avatar_url,
            'provider' => $user->provider,
            'preferred_language' => $user->preferred_language,
            'status' => $user->status,
            'personal_statuses' => is_array($user->personal_statuses) ? array_values($user->personal_statuses) : [],
            'personal_onboarding_completed' => $user->personal_onboarding_completed_at !== null,
            'account_type' => 'user',
            'job_search_preferences' => is_array($user->job_search_preferences) ? $user->job_search_preferences : [],
            'job_search_profile_updated_at' => $user->job_search_profile_updated_at?->toIso8601String(),
            'unread_job_application_notifications' => collect(is_array($user->job_application_notifications) ? $user->job_application_notifications : [])->where('read', false)->count(),
            'has_company' => $user->employeeLinks()->where('status', 'active')->exists(),
            'company_requests' => $user->companyRequests()
                ->select(['id', 'company_name', 'status', 'created_at'])
                ->latest()
                ->take(5)
                ->get(),
            'employee_links' => $user->employeeLinks()
                ->with('company:id,name')
                ->where('status', 'active')
                ->get()
                ->map(fn ($link) => [
                    'company_id' => $link->company_id,
                    'company_name' => $link->company?->name,
                    'employee_id' => $link->employee_id,
                ]),
        ];
    }
}
