<?php

declare(strict_types=1);

namespace App\Core\Auth\Interfaces\Api\V1;

use App\Core\Auth\Application\Actions\ChangePasswordAction;
use App\Core\Auth\Application\Actions\LoginAction;
use App\Core\Auth\Application\Actions\LogoutAction;
use App\Core\Auth\Application\Actions\RefreshTokenAction;
use App\Core\Auth\Application\Actions\RegisterAction;
use App\Core\Auth\Application\Actions\UpdateProfileAction;
use App\Core\Auth\Domain\Exceptions\TwoFactorException;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Auth\Infrastructure\Services\TwoFactorAuthService;
use App\Core\Auth\Interfaces\Requests\ChangePasswordRequest;
use App\Core\Auth\Interfaces\Requests\LoginRequest;
use App\Core\Auth\Interfaces\Requests\StoreRegistrationRequest;
use App\Core\Auth\Interfaces\Requests\UpdateProfileRequest;
use App\Exceptions\AccountSuspendedException;
use App\Exceptions\CompanyNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\EmployeeResource;
use App\Modules\HR\Application\DTOs\UpdateEmployeeDTO;
use App\Shared\Models\Language;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;

class AuthController extends Controller
{
    public function __construct(
        private readonly LoginAction $loginAction,
        private readonly RegisterAction $registerAction,
        private readonly LogoutAction $logoutAction,
        private readonly RefreshTokenAction $refreshTokenAction,
        private readonly UpdateProfileAction $updateProfileAction,
        private readonly ChangePasswordAction $changePasswordAction,
        private readonly TwoFactorAuthService $twoFactorService,
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->loginAction->execute(
            email: $request->validated('email'),
            password: $request->validated('password'),
            deviceName: $request->validated('device_name'),
        );

        $employee = $result['employee'];

        // #5436 : 2FA — le token vient d'être créé par AuthService ; s'il ne
        // doit pas être délivré (challenge ou politique), on le révoque.
        if ($employee->two_fa_enabled_at !== null) {
            // Appareil de confiance (cookie signé) : pas de challenge.
            $expected = $this->twoFactorService->rememberCookieValue($employee);
            $provided = $request->cookie('mfa_remember_'.$employee->id);
            if (is_string($provided) && hash_equals($expected, $provided)) {
                return (new EmployeeResource($employee))
                    ->additional([
                        'token' => $result['token'],
                        'token_type' => $result['token_type'],
                        'token_expires_at' => $result['token_expires_at'],
                    ])
                    ->response();
            }

            $employee->tokens()->latest('id')->first()?->delete();

            $deviceName = $request->validated('device_name');

            $challenge = $this->twoFactorService->issueChallenge([
                'employee_id' => $employee->id,
                'company_id' => (string) $employee->company_id,
                'tenant_schema' => $result['tenant_schema'],
                'email' => (string) $employee->email,
                'device_name' => is_string($deviceName) ? $deviceName : null,
            ]);

            return new JsonResponse([
                'mfa_challenge' => true,
                'mfa_challenge_token' => $challenge['token'],
                'mfa_challenge_expires_in' => $challenge['expires_in'],
            ]);
        }

        // Politique tenant : rôle sensible + 2FA non activée → blocage.
        if ($this->twoFactorService->requiresMfa($employee)) {
            $employee->tokens()->latest('id')->first()?->delete();

            throw TwoFactorException::required();
        }

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
        // #2617 (main) : inscription réservée aux invitations valides — le
        // RegisterAction refuse sans invitation_token et rattache l'employé au
        // company_id de l'invitation (plus d'employé orphelin, #2636).
        /** @var array{first_name: string, last_name: string, email: string, password: string, invitation_token?: string|null, device_name?: string} $validated */
        $validated = $request->validated();

        $result = $this->registerAction->execute($validated);

        return (new EmployeeResource($result['employee']))
            ->additional([
                'token' => $result['token'],
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
        return new JsonResponse(['status' => 'ok', ...$tokenResult]);
    }

    public function refreshToken(Request $request): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();

        $result = $this->refreshTokenAction->execute($employee);

        // #4698 (audit 360° 2026-08-16) : enveloppe {data: {...}} alignée sur
        // login/register/me — avant, le résultat était renvoyé à plat.
        return new JsonResponse(['data' => $result]);
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
        // Issue #5170 : garde de configuration. En prod (Render), des
        // GOOGLE_CLIENT_ID/GOOGLE_CLIENT_SECRET/GOOGLE_REDIRECT_URL absents
        // faisaient planter Socialite au moment de construire l'URL →
        // exception non gérée → 500 INTERNAL_ERROR (page JSON brute côté
        // vitrine). On échoue rapidement avec un 503 explicite + log
        // opérationnel (quelles variables manquent) au lieu d'un 500 générique.
        if (! $this->googleOauthConfigured()) {
            Log::error('auth.google.not_configured', [
                'client_id' => filled(config('services.google.client_id')),
                'client_secret' => filled(config('services.google.client_secret')),
                'redirect' => filled(config('services.google.redirect')),
            ]);

            return new JsonResponse([
                'error' => 'GOOGLE_OAUTH_NOT_CONFIGURED',
                'message' => __('errors.GOOGLE_OAUTH_NOT_CONFIGURED'),
            ], 503);
        }

        // Issue #2619 : état aléatoire en session (anti-CSRF login) — validé
        // au callback. Plus de Socialite stateless sans protection.
        $state = Str::random(40);
        session(['google_oauth_state' => $state]);

        /** @var GoogleProvider $google */
        $google = Socialite::driver('google');

        return $google->with(['state' => $state])->redirect();
    }

    /**
     * Issue #5170 : la redirection Google nécessite les trois variables de
     * configuration (client_id, client_secret, redirect). Absentes → 503
     * explicite au lieu d'un 500 générique (constat prod 2026-08-20).
     */
    private function googleOauthConfigured(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'))
            && filled(config('services.google.redirect'));
    }

    public function handleGoogleCallback(Request $request): JsonResponse
    {
        // Issue #2619 : validation du state — callback sans state ou avec un
        // state inconnu → 400 (pas de login).
        $expected = session('google_oauth_state');
        $provided = $request->query('state');
        if (! is_string($expected) || ! is_string($provided) || ! hash_equals($expected, $provided)) {
            return new JsonResponse(['error' => 'INVALID_OAUTH_STATE'], 400);
        }
        session()->forget('google_oauth_state');

        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
        } catch (\Exception $e) {
            Log::error('auth.google.callback_failed', ['error' => $e->getMessage()]);

            return new JsonResponse([
                'error' => 'GOOGLE_AUTH_FAILED',
                'message' => __('errors.GOOGLE_AUTH_FAILED'),
            ], 422);
        }

        /** @var Employee|null $employee */
        $employee = Employee::withoutGlobalScopes()->where('email', $googleUser->getEmail())->first();

        if (! $employee) {
            // Issue #3724 : pas d'auto-provisionnement silencieux en production.
            // Le flux d'invitation (#2617) crée toujours la ligne employé en
            // amont — un email totalement inconnu n'a donc aucun chemin légitime
            // ici. L'auto-création reste possible uniquement sur les environnements
            // de démo explicitement configurés (DEMO_MODE_ENABLED=true), en
            // parité avec le 401 de handleGoogleToken.
            if (! config('app.demo_mode_enabled')) {
                return new JsonResponse([
                    'error' => 'UNKNOWN_ACCOUNT',
                    'message' => 'No account exists for this Google email. Ask your administrator for an invitation.',
                ], 401);
            }

            /** @var Employee $employee */
            $employee = Employee::forceCreate([
                'first_name' => $googleUser->offsetGet('given_name') ?? $googleUser->getName(),
                'last_name' => $googleUser->offsetGet('family_name') ?? '',
                'email' => $googleUser->getEmail(),
                'password_hash' => Hash::make(str()->random(24)),
            ]);

            // Sensitive fields set explicitly (not mass-assignable, #3597)
            $employee->role = 'ordinary';
            $employee->status = 'active';
            $employee->save();
        }

        // Sécurité #2630 : un employé suspendu (ou société suspendue/expirée)
        // ne peut pas s'authentifier, y compris via Google.
        if ($employee->status !== 'active') {
            throw new AccountSuspendedException;
        }
        if ($employee->company_id) {
            $company = $employee->company;
            if ($company && in_array($company->status, ['suspended', 'expired'], true)) {
                throw new AccountSuspendedException;
            }
        }

        $token = $employee->createToken('google-auth');

        return (new EmployeeResource($employee))
            ->additional([
                'token' => $token->plainTextToken,
                'token_type' => 'Bearer',
            ])
            ->response()
            ->setStatusCode($employee->wasRecentlyCreated ? 201 : 200);
    }

    public function handleGoogleToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'access_token' => 'required|string',
            'device_name' => 'nullable|string|max:255',
        ]);

        try {
            $googleUser = Socialite::driver('google')->stateless()->userFromToken($validated['access_token']);
        } catch (\Exception $e) {
            Log::error('auth.google.token_exchange_failed', ['error' => $e->getMessage()]);

            return new JsonResponse([
                'error' => 'GOOGLE_TOKEN_INVALID',
                'message' => __('errors.GOOGLE_TOKEN_INVALID'),
            ], 422);
        }

        /** @var Employee|null $employee */
        $employee = Employee::withoutGlobalScopes()->where('email', $googleUser->getEmail())->first();

        if (! $employee) {
            return new JsonResponse(['error' => 'EMPLOYEE_NOT_FOUND', 'message' => __('errors.GOOGLE_ACCOUNT_NOT_FOUND')], 401);
        }

        // Sécurité #2630 : statut employé + société (mêmes gardes que le login classique).
        if ($employee->status !== 'active') {
            throw new AccountSuspendedException;
        }
        if ($employee->company_id) {
            $company = $employee->company;
            if ($company && in_array($company->status, ['suspended', 'expired'], true)) {
                throw new AccountSuspendedException;
            }
        }

        $tokenName = $validated['device_name'] ?? 'google-mobile';
        $token = $employee->createToken($tokenName);

        return (new EmployeeResource($employee))
            ->additional([
                'token' => $token->plainTextToken,
                'token_type' => 'Bearer',
            ])
            ->response();
    }
}
