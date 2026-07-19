<?php

declare(strict_types=1);

namespace App\Core\Auth\Interfaces\Api\V1;

use App\Core\Auth\Application\Actions\ChangePasswordAction;
use App\Core\Auth\Application\Actions\LoginAction;
use App\Core\Auth\Application\Actions\LogoutAction;
use App\Core\Auth\Application\Actions\RefreshTokenAction;
use App\Core\Auth\Application\Actions\RegisterAction;
use App\Core\Auth\Application\Actions\UpdateProfileAction;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Auth\Interfaces\Requests\ChangePasswordRequest;
use App\Core\Auth\Interfaces\Requests\LoginRequest;
use App\Core\Auth\Interfaces\Requests\StoreRegistrationRequest;
use App\Core\Auth\Interfaces\Requests\UpdateProfileRequest;
use App\Modules\HR\Application\DTOs\UpdateEmployeeDTO;
use App\Exceptions\CompanyNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\EmployeeResource;
use App\Shared\Models\Language;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    public function __construct(
        private readonly LoginAction $loginAction,
        private readonly RegisterAction $registerAction,
        private readonly LogoutAction $logoutAction,
        private readonly RefreshTokenAction $refreshTokenAction,
        private readonly UpdateProfileAction $updateProfileAction,
        private readonly ChangePasswordAction $changePasswordAction,
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->loginAction->execute(
            email: $request->validated('email'),
            password: $request->validated('password'),
            deviceName: $request->validated('device_name'),
        );

        $employee = $result['employee'];

        return (new EmployeeResource($employee))
            ->additional([
                'token'            => $result['token'],
                'token_type'       => $result['token_type'],
                'token_expires_at' => $result['token_expires_at'],
            ])
            ->response();
    }

    public function register(StoreRegistrationRequest $request): JsonResponse
    {
        $result = $this->registerAction->execute($request->validated());

        return (new EmployeeResource($result['employee']))
            ->additional([
                'token'      => $result['token'],
                'token_type' => $result['token_type'],
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

        $fresh = $this->updateProfileAction->execute($employee, $dto);

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

        $tokenResult = $this->changePasswordAction->execute(
            employee: $employee,
            currentPassword: $request->validated('current_password'),
            newPassword: $request->validated('new_password'),
        );

        // All previous Sanctum tokens were revoked by the action; a fresh one
        // for the current device is returned so the caller stays logged in.
        return new JsonResponse(['status' => 'ok', ...($tokenResult ?? [])]);
    }

    public function refreshToken(Request $request): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();

        $result = $this->refreshTokenAction->execute($employee);

        return new JsonResponse($result);
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();

        $this->logoutAction->execute($employee);

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

        /** @var Employee|null $employee */
        $employee = Employee::withoutGlobalScopes()->where('email', $googleUser->getEmail())->first();

        if (! $employee) {
            /** @var Employee $employee */
            $employee = Employee::create([
                'first_name'    => $googleUser->offsetGet('given_name') ?? $googleUser->getName(),
                'last_name'     => $googleUser->offsetGet('family_name') ?? '',
                'email'         => $googleUser->getEmail(),
                'password_hash' => Hash::make(str()->random(24)),
                'role'          => 'ordinary',
                'status'        => 'active',
            ]);
        }

        $token = $employee->createToken('google-auth');

        return (new EmployeeResource($employee))
            ->additional([
                'token'      => $token->plainTextToken,
                'token_type' => 'Bearer',
            ])
            ->response()
            ->setStatusCode($employee->wasRecentlyCreated ? 201 : 200);
    }

    public function handleGoogleToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'access_token' => 'required|string',
            'device_name'  => 'nullable|string|max:255',
        ]);

        try {
            $googleUser = Socialite::driver('google')->stateless()->userFromToken($validated['access_token']);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'GOOGLE_TOKEN_INVALID', 'message' => $e->getMessage()], 422);
        }

        /** @var Employee|null $employee */
        $employee = Employee::withoutGlobalScopes()->where('email', $googleUser->getEmail())->first();

        if (! $employee) {
            return new JsonResponse(['error' => 'EMPLOYEE_NOT_FOUND', 'message' => 'No account found for this Google account.'], 401);
        }

        $tokenName = $validated['device_name'] ?? 'google-mobile';
        $token = $employee->createToken($tokenName);

        return (new EmployeeResource($employee))
            ->additional([
                'token'      => $token->plainTextToken,
                'token_type' => 'Bearer',
            ])
            ->response();
    }
}

