<?php

declare(strict_types=1);

namespace App\Core\Auth\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Application\Actions\ChangePasswordAction;
use App\Core\Auth\Application\Actions\HandleGoogleCallbackAction;
use App\Core\Auth\Application\Actions\HandleGoogleTokenAction;
use App\Core\Auth\Application\Actions\LoginAction;
use App\Core\Auth\Application\Actions\LogoutAction;
use App\Core\Auth\Application\Actions\RefreshTokenAction;
use App\Core\Auth\Application\Actions\RegisterAction;
use App\Core\Auth\Application\Actions\StartGoogleRedirectAction;
use App\Core\Auth\Application\Actions\UpdateProfileAction;
use App\Core\Auth\Application\DTOs\GoogleSsoResult;
use App\Core\Auth\Domain\Exceptions\TwoFactorException;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Auth\Infrastructure\Services\TwoFactorAuthService;
use App\Core\Auth\Interfaces\Api\V1\Requests\ChangePasswordRequest;
use App\Core\Auth\Interfaces\Api\V1\Requests\LoginRequest;
use App\Core\Auth\Interfaces\Api\V1\Requests\StoreRegistrationRequest;
use App\Core\Auth\Interfaces\Api\V1\Requests\UpdateProfileRequest;
use App\Exceptions\CompanyNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\EmployeeResource;
use App\Modules\HR\Application\DTOs\UpdateEmployeeDTO;
use App\Shared\Models\Language;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
        private readonly StartGoogleRedirectAction $startGoogleRedirectAction,
        private readonly HandleGoogleCallbackAction $handleGoogleCallbackAction,
        private readonly HandleGoogleTokenAction $handleGoogleTokenAction,
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

        $oldEmail = (string) $employee->email;
        $newEmail = $request->validated('email');
        $emailChanged = is_string($newEmail) && strtolower($newEmail) !== strtolower($oldEmail);

        $dto = UpdateEmployeeDTO::fromRequest($request);
        $fresh = $this->updateProfileAction->execute($employee, $dto);

        // Issue #5587 : trace d'audit + notification de l'ancienne adresse lors
        // d'un changement d'email (défense en profondeur — le mot de passe a déjà
        // été validé dans UpdateProfileRequest::after()).
        if ($emailChanged) {
            Log::info('auth.profile.email_changed', [
                'employee_id' => $employee->id,
                'company_id' => (string) $employee->company_id,
                'old_email' => $oldEmail,
                // Ne logguer que le domaine du nouvel email pour éviter de
                // stocker des données personnelles en clair dans les logs.
                'new_email_domain' => substr($newEmail, (int) strrpos($newEmail, '@')),
            ]);
        }

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

    /**
     * #7962 — le contrôleur ne garde que le transport : générer le `state`
     * anti-CSRF (#2619), le poser en session avec l'intention du parcours, et
     * traduire le résultat de l'Action en réponse HTTP.
     */
    public function redirectToGoogle(Request $request): mixed
    {
        // Issue #2619 : état aléatoire en session (anti-CSRF login) — validé
        // au callback. Plus de Socialite stateless sans protection.
        $state = Str::random(40);

        // QA onboarding 2026-09-14 — intention du parcours. La CONNEXION reste
        // « invitation-first » (issue #3724 : aucun auto-provisionnement depuis
        // un callback OAuth). L'INSCRIPTION (bouton « Continuer avec Google »
        // de /signup) demande explicitement la CRÉATION d'un compte : elle
        // renvoie l'identité vérifiée par Google pour pré-remplir le tunnel
        // d'essai, qui reste seul responsable de la création du tenant.
        // L'intention est stockée en session à côté du state (même mécanique,
        // aucune donnée non fiable n'est injectée dans l'URL de retour).
        $intent = $request->query('intent') === 'signup' ? 'signup' : 'login';
        $plan = $request->query('plan');

        session([
            'google_oauth_state' => $state,
            'google_oauth_intent' => $intent,
            'google_oauth_plan' => is_string($plan) ? mb_substr($plan, 0, 80) : null,
        ]);

        $result = $this->startGoogleRedirectAction->execute($state);

        if ($result->outcome === 'redirect') {
            return redirect()->away((string) $result->redirectUrl);
        }

        return $this->errorResponse($result);
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

        // Intention lue une seule fois : elle ne doit jamais survivre au
        // parcours qui l'a posée (sinon une connexion ultérieure hériterait
        // d'une intention d'inscription).
        $googleIntent = session('google_oauth_intent');
        $googlePlan = session('google_oauth_plan');
        session()->forget(['google_oauth_intent', 'google_oauth_plan']);

        $result = $this->handleGoogleCallbackAction->execute(
            intent: is_string($googleIntent) ? $googleIntent : 'login',
            plan: is_string($googlePlan) ? $googlePlan : null,
            rememberCookies: $this->rememberCookieValues($request),
        );

        return $this->ssoResponse($result);
    }

    public function handleGoogleToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'access_token' => 'required|string',
            'device_name' => 'nullable|string|max:255',
        ]);

        $result = $this->handleGoogleTokenAction->execute(
            accessToken: (string) $validated['access_token'],
            deviceName: isset($validated['device_name']) ? (string) $validated['device_name'] : null,
        );

        return $this->ssoResponse($result);
    }

    /**
     * Le cookie « appareil de confiance » est nommé `mfa_remember_<employee_id>`,
     * donc son nom dépend de l'employé — inconnu avant résolution de l'identité
     * Google. Le contrôleur ne peut donc pas lire « le » cookie de l'employé ;
     * il transmet la valeur de chaque cookie `mfa_remember_*` présentée, indexée
     * par id, et l'Action décide (hash_equals) si l'un d'eux correspond.
     *
     * @return array<int, string>
     */
    private function rememberCookieValues(Request $request): array
    {
        $cookies = [];

        foreach ($request->cookies->all() as $name => $value) {
            $name = (string) $name;

            if (! is_string($value) || ! str_starts_with($name, 'mfa_remember_')) {
                continue;
            }

            $employeeId = substr($name, strlen('mfa_remember_'));
            if ($employeeId !== '' && ctype_digit($employeeId)) {
                $cookies[] = $value;
            }
        }

        return $cookies;
    }

    private function ssoResponse(GoogleSsoResult $result): JsonResponse
    {
        return match ($result->outcome) {
            'authenticated' => (new EmployeeResource($result->employee))
                ->additional([
                    'token' => $result->token,
                    'token_type' => $result->tokenType,
                ])
                ->response(),
            'mfa_challenge' => new JsonResponse([
                'mfa_challenge' => true,
                'mfa_challenge_token' => $result->mfaChallengeToken,
                'mfa_challenge_expires_in' => $result->mfaChallengeExpiresIn,
            ]),
            'signup' => new JsonResponse([
                'success' => true,
                'data' => [
                    'signup' => true,
                    'email' => $result->signupEmail,
                    'first_name' => $result->signupFirstName,
                    'last_name' => $result->signupLastName,
                    'plan' => $result->signupPlan,
                ],
            ]),
            default => $this->errorResponse($result),
        };
    }

    private function errorResponse(GoogleSsoResult $result): JsonResponse
    {
        return new JsonResponse([
            'error' => $result->errorCode,
            'message' => $result->errorMessage,
        ], $result->errorStatus > 0 ? $result->errorStatus : 500);
    }
}
