<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\UserAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class UserAuthController extends Controller
{
    public function __construct(
        private readonly UserAuthService $userAuthService,
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
            'google_id' => ['required', 'string'],
            'email' => ['required', 'email'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'avatar_url' => ['nullable', 'url', 'max:500'],
        ]);

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

        return new JsonResponse([
            'data' => $this->formatUser($user->fresh()),
        ]);
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
