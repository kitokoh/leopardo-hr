<?php

namespace App\Http\Controllers\Api\V1;

use App\DTOs\UpdateEmployeeDTO;
use App\Exceptions\CompanyNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ChangePasswordRequest;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Requests\Api\V1\StoreRegistrationRequest;
use App\Http\Requests\Api\V1\UpdateProfileRequest;
use App\Http\Resources\Api\V1\EmployeeResource;
use App\Models\Employee;
use App\Models\Language;
use App\Services\AuthService;
use App\Services\EmployeeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly EmployeeService $employeeService,
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login(
            email: $request->validated('email'),
            password: $request->validated('password'),
            deviceName: $request->validated('device_name')
        );

        $employee = $result['employee'];

        return (new EmployeeResource($employee))
            ->additional([
                'token' => $result['token'],
                'token_type' => $result['token_type'],
                'token_expires_at' => $result['token_expires_at'],
            ])
            ->response();
    }

    public function register(StoreRegistrationRequest $request): JsonResponse
    {
        $employee = Employee::create([
            'first_name' => $request->validated('first_name'),
            'last_name' => $request->validated('last_name'),
            'email' => $request->validated('email'),
            'password_hash' => Hash::make($request->validated('password')),
            'role' => 'ordinary',
            'status' => 'active',
        ]);

        $tokenName = $request->validated('device_name') ?: 'api';
        $token = $employee->createToken($tokenName);

        return (new EmployeeResource($employee))
            ->additional([
                'token' => $token->plainTextToken,
                'token_type' => 'Bearer',
            ])
            ->response()
            ->setStatusCode(201);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();

        if ($employee->company === null && $employee->role !== 'ordinary') {
            throw new CompanyNotFoundException;
        }

        return (new EmployeeResource($employee))->response();
    }

    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();
        $dto = UpdateEmployeeDTO::fromRequest($request);

        $employee = $this->employeeService->update($employee, $employee, $dto);

        /** @var Employee $fresh */
        $fresh = $employee->fresh();

        return (new EmployeeResource($fresh))->response();
    }

    public function updateLanguage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'language' => [
                'required',
                'string',
                'size:2',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_string($value) || ! Language::isSupported($value)) {
                        $fail(__('validation.in', ['attribute' => $attribute]));
                    }
                },
            ],
        ]);

        /** @var Employee $employee */
        $employee = $request->user();
        $employee->preferred_language = strtolower($validated['language']);
        $employee->save();

        app()->setLocale($employee->preferred_language);

        /** @var Employee $fresh */
        $fresh = $employee->fresh();

        return (new EmployeeResource($fresh))->response();
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();

        if (! Hash::check($request->validated('current_password'), $employee->password_hash)) {
            return new JsonResponse([
                'error' => 'INVALID_CURRENT_PASSWORD',
                'message' => 'INVALID_CURRENT_PASSWORD',
                'localized_message' => __('errors.INVALID_CURRENT_PASSWORD'),
            ], 422);
        }

        $employee->password_hash = Hash::make($request->validated('new_password'));
        $employee->save();

        return new JsonResponse([
            'status' => 'ok',
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();
        if ($token) {
            $token->delete();
        }

        return new JsonResponse(['message' => 'LOGGED_OUT']);
    }

    public function redirectToGoogle(): mixed
    {
        return Socialite::driver('google')->stateless()->redirect();
    }

    public function handleGoogleCallback(): JsonResponse
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'GOOGLE_AUTH_FAILED', 'message' => $e->getMessage()], 422);
        }

        $employee = Employee::withoutGlobalScopes()->where('email', $googleUser->getEmail())->first();

        if (! $employee) {
            $employee = Employee::create([
                'first_name' => $googleUser->offsetGet('given_name') ?? $googleUser->getName(),
                'last_name' => $googleUser->offsetGet('family_name') ?? '',
                'email' => $googleUser->getEmail(),
                'password_hash' => Hash::make(str()->random(24)),
                'role' => 'ordinary',
                'status' => 'active',
            ]);
        }

        $token = $employee->createToken('google-auth');

        return (new EmployeeResource($employee))
            ->additional([
                'token' => $token->plainTextToken,
                'token_type' => 'Bearer',
            ])
            ->response()
            ->setStatusCode(201);
    }

    public function handleGoogleToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => 'required|string',
            'device_name' => 'nullable|string',
        ]);

        try {
            $googleUser = Socialite::driver('google')->stateless()->userFromToken($validated['token']);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'GOOGLE_AUTH_FAILED', 'message' => $e->getMessage()], 422);
        }

        $employee = Employee::withoutGlobalScopes()->where('email', $googleUser->getEmail())->first();

        if (! $employee) {
            $employee = Employee::create([
                'first_name' => $googleUser->offsetGet('given_name') ?? $googleUser->getName(),
                'last_name' => $googleUser->offsetGet('family_name') ?? '',
                'email' => $googleUser->getEmail(),
                'password_hash' => Hash::make(str()->random(24)),
                'role' => 'ordinary',
                'status' => 'active',
            ]);
        }

        $tokenName = $validated['device_name'] ?? 'google-auth';
        $token = $employee->createToken($tokenName);

        return (new EmployeeResource($employee))
            ->additional([
                'token' => $token->plainTextToken,
                'token_type' => 'Bearer',
            ])
            ->response()
            ->setStatusCode(201);
    }
}
