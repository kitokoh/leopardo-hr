<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\UserAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use App\Http\Requests\Api\V1\Auth\ChangePasswordUserAuthRequest;
use App\Http\Requests\Api\V1\Auth\GoogleSignInUserAuthRequest;
use App\Http\Requests\Api\V1\Auth\LoginUserAuthRequest;
use App\Http\Requests\Api\V1\Auth\RegisterUserAuthRequest;
use App\Http\Requests\Api\V1\Auth\UpdateProfileUserAuthRequest;

class UserAuthController extends Controller
{
    public function __construct(
        private readonly UserAuthService $userAuthService,
    ) {}

    public function register(RegisterUserAuthRequest $request): JsonResponse
    {
        $validated = $request->validated();

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

    public function login(LoginUserAuthRequest $request): JsonResponse
    {
        $validated = $request->validated();

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

    public function googleSignIn(GoogleSignInUserAuthRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = $this->userAuthService->googleSignIn(
            googleId: $validated['google_id'],
            email: $validated['email'],
            firstName: $validated['first_name'],
            lastName: $validated['last_name'],
            avatarUrl: $validated['avatar_url'] ?? null,
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

    public function updateProfile(UpdateProfileUserAuthRequest $request): JsonResponse
    {
        $validated = $request->validated();

        /** @var User $user */
        $user = $request->user('user_api');
        $user->update($validated);

        /** @var User $fresh */
        $fresh = $user->fresh();

        return new JsonResponse([
            'data' => $this->formatUser($fresh),
        ]);
    }

    public function changePassword(ChangePasswordUserAuthRequest $request): JsonResponse
    {
        $validated = $request->validated();

        /** @var User $user */
        $user = $request->user('user_api');

        if ($user->password_hash && ! Hash::check($validated['current_password'], $user->password_hash)) {
            return new JsonResponse([
                'error' => 'INVALID_CURRENT_PASSWORD',
                'message' => __('errors.INVALID_CURRENT_PASSWORD'),
            ], 422);
        }

        $user->update(['password_hash' => Hash::make($validated['new_password'])]);

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
            'account_type' => 'user',
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
