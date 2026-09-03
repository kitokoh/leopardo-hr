<?php

declare(strict_types=1);

namespace App\Core\Auth\Interfaces\Api\V1;

use App\Core\Auth\Infrastructure\Services\SuperAdminService;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class PlatformAuthController extends Controller
{
    /**
     * #6563 (audit auth 2026-08-31) — verrouillage du login API super-admin :
     * le throttle IP seul ne protège pas un mot de passe faible (attaques
     * distribuées). Compteur PAR COMPTE (email) en cache partagé : 5 échecs →
     * verrou 15 min (mêmes seuils que le verrouillage employé, AuthService).
     */
    private const LOCKOUT_MAX_ATTEMPTS = 5;

    private const LOCKOUT_DURATION_MINUTES = 15;

    public function __construct(
        private readonly SuperAdminService $superAdminService,
    ) {}

    public function login(Request $request): JsonResponse
    {
        /** @var array{email: string, password: string, device_name?: string, two_fa_code?: string} $validated */
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
            'two_fa_code' => ['nullable', 'string'],
        ]);

        $email = strtolower(trim((string) $validated['email']));
        $lockKey = 'platform:auth:lockout:'.$email;

        // Compte verrouillé (trop d'échecs récents) → refus immédiat, même
        // avec le bon mot de passe (anti brute-force, #6563).
        $lockedUntil = Cache::get($lockKey);
        if ($lockedUntil instanceof \DateTimeInterface && $lockedUntil->getTimestamp() > time()) {
            return new JsonResponse([
                'error' => 'ACCOUNT_LOCKED',
                'message' => 'ACCOUNT_LOCKED',
                'localized_message' => __('api_errors.ACCOUNT_LOCKED'),
            ], 423);
        }
        if ($lockedUntil !== null) {
            Cache::forget($lockKey);
            Cache::forget($lockKey.':attempts');
        }

        /** @var SuperAdmin|null $superAdmin */
        $superAdmin = SuperAdmin::query()->where('email', $email)->first();

        if (! $superAdmin || ! Hash::check($validated['password'], (string) $superAdmin->password_hash)) {
            $attemptsKey = $lockKey.':attempts';
            $cachedAttempts = Cache::get($attemptsKey, 0);
            $attempts = (is_int($cachedAttempts) ? $cachedAttempts : 0) + 1;
            Cache::put($attemptsKey, $attempts, now()->addMinutes(self::LOCKOUT_DURATION_MINUTES));

            if ($attempts >= self::LOCKOUT_MAX_ATTEMPTS) {
                Cache::put($lockKey, now()->addMinutes(self::LOCKOUT_DURATION_MINUTES), now()->addMinutes(self::LOCKOUT_DURATION_MINUTES));
                Cache::forget($attemptsKey);

                return new JsonResponse([
                    'error' => 'ACCOUNT_LOCKED',
                    'message' => 'ACCOUNT_LOCKED',
                    'localized_message' => __('api_errors.ACCOUNT_LOCKED'),
                ], 423);
            }

            return new JsonResponse([
                'error' => 'INVALID_CREDENTIALS',
                'message' => 'INVALID_CREDENTIALS',
                'localized_message' => __('errors.INVALID_CREDENTIALS'),
            ], 401);
        }

        // Succès : reset du compteur de verrouillage (#6563).
        Cache::forget($lockKey);
        Cache::forget($lockKey.':attempts');

        // Sécurité #2630 : un super-admin suspendu/désactivé ne peut pas se connecter.
        if ($superAdmin->status !== 'active') {
            return new JsonResponse([
                'error' => 'ACCOUNT_SUSPENDED',
                'message' => 'ACCOUNT_SUSPENDED',
                'localized_message' => __('errors.ACCOUNT_SUSPENDED'),
            ], 403);
        }

        // Check 2FA if enabled
        if ($superAdmin->two_fa_secret) {
            if (! isset($validated['two_fa_code'])) {
                return new JsonResponse([
                    'error' => 'TWO_FA_REQUIRED',
                    'message' => __('auth.twofa_code_required'),
                ], 202); // 202 Accepted but further action needed
            }

            if (! $this->superAdminService->verifyCode($superAdmin, $validated['two_fa_code'])) {
                return new JsonResponse([
                    'error' => 'INVALID_2FA_CODE',
                    'message' => __('auth.twofa_code_invalid'),
                ], 401);
            }
        }

        $token = $superAdmin->createToken($validated['device_name'] ?? 'platform-api')->plainTextToken;

        return new JsonResponse([
            'data' => [
                'id' => $superAdmin->id,
                'name' => $superAdmin->name,
                'email' => $superAdmin->email,
                'role' => 'super_admin',
                'two_fa_enabled' => (bool) $superAdmin->two_fa_secret,
            ],
            'token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var SuperAdmin $superAdmin */
        $superAdmin = $request->user('super_admin_api');

        return new JsonResponse([
            'data' => [
                'id' => $superAdmin->id,
                'name' => $superAdmin->name,
                'email' => $superAdmin->email,
                'role' => 'super_admin',
                'two_fa_enabled' => (bool) $superAdmin->two_fa_secret,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user('super_admin_api')?->currentAccessToken()?->delete();

        return new JsonResponse(['status' => 'ok']);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        // #6563 (audit auth) : le changement d'email exige le mot de passe
        // courant (comme pour les employés) — un token volé ne suffit plus à
        // détourner le compte super-admin vers une adresse contrôlée par
        // l'attaquant (prise de contrôle + reset du mot de passe).
        $rules = [
            'name' => ['sometimes', 'string', 'max:100'],
            'email' => ['sometimes', 'email', 'max:150'],
        ];

        /** @var SuperAdmin $superAdmin */
        $superAdmin = $request->user('super_admin_api');

        if ($request->filled('email') && $request->input('email') !== $superAdmin->email) {
            $rules['current_password'] = ['required', 'string'];
        }

        /** @var array{name?: string, email?: string, current_password?: string} $validated */
        $validated = $request->validate($rules);

        if (isset($validated['email']) && $validated['email'] !== $superAdmin->email) {
            if (! Hash::check($validated['current_password'] ?? '', (string) $superAdmin->password_hash)) {
                return new JsonResponse([
                    'error' => 'INVALID_PASSWORD',
                    'message' => 'INVALID_PASSWORD',
                    'localized_message' => __('auth.current_password_incorrect'),
                ], 401);
            }

            $emailTaken = SuperAdmin::query()
                ->where('email', $validated['email'])
                ->where('id', '!=', $superAdmin->id)
                ->exists();

            if ($emailTaken) {
                return new JsonResponse([
                    'error' => 'EMAIL_ALREADY_TAKEN',
                    'message' => __('auth.email_already_used'),
                ], 422);
            }

            $superAdmin->email = $validated['email'];
        }

        if (isset($validated['name'])) {
            $superAdmin->name = $validated['name'];
        }

        $superAdmin->save();

        return new JsonResponse([
            'data' => [
                'id' => $superAdmin->id,
                'name' => $superAdmin->name,
                'email' => $superAdmin->email,
                'role' => 'super_admin',
                'two_fa_enabled' => (bool) $superAdmin->two_fa_secret,
            ],
        ]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        /** @var array{current_password: string, new_password: string} $validated */
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            // Issue #5620 : min 8 caractères + au moins 1 chiffre.
            'new_password' => ['required', 'string', Password::min(8)->numbers(), 'max:255', 'confirmed'],
        ]);

        /** @var SuperAdmin $superAdmin */
        $superAdmin = $request->user('super_admin_api');

        if (! Hash::check($validated['current_password'], (string) $superAdmin->password_hash)) {
            return new JsonResponse([
                'error' => 'INVALID_PASSWORD',
                'message' => __('auth.current_password_incorrect'),
            ], 401);
        }

        $superAdmin->password_hash = Hash::make($validated['new_password']);
        $superAdmin->save();

        // Revoke all other API tokens so a leaked password can't keep an active session alive.
        $currentTokenId = $superAdmin->currentAccessToken()->id;
        $superAdmin->tokens()
            ->where('id', '!=', $currentTokenId)
            ->delete();

        return new JsonResponse(['status' => 'ok']);
    }

    public function setup2fa(Request $request): JsonResponse
    {
        /** @var SuperAdmin $superAdmin */
        $superAdmin = $request->user('super_admin_api');

        if ($superAdmin->two_fa_secret) {
            return new JsonResponse([
                'error' => 'ALREADY_ENABLED',
                'message' => __('auth.twofa_already_enabled'),
            ], 400);
        }

        $secret = $this->superAdminService->generateSecret();
        $qrCodeUrl = $this->superAdminService->getQrCodeUrl($superAdmin, $secret);

        Cache::put($this->pendingTwoFaSecretCacheKey($superAdmin), $secret, now()->addMinutes(10));

        return new JsonResponse([
            'data' => [
                'secret' => $secret,
                'qr_code_url' => $qrCodeUrl,
            ],
        ]);
    }

    public function enable2fa(Request $request): JsonResponse
    {
        /** @var array{code: string} $validated */
        $validated = $request->validate([
            'code' => ['required', 'string'],
        ]);

        /** @var SuperAdmin $superAdmin */
        $superAdmin = $request->user('super_admin_api');

        if ($superAdmin->two_fa_secret) {
            return new JsonResponse([
                'error' => 'ALREADY_ENABLED',
                'message' => __('auth.twofa_already_enabled'),
            ], 400);
        }

        /** @var string|null $secret */
        $secret = Cache::get($this->pendingTwoFaSecretCacheKey($superAdmin));

        if (! $secret) {
            return new JsonResponse([
                'error' => 'SETUP_REQUIRED',
                'message' => __('auth.twofa_not_setup'),
            ], 400);
        }

        $superAdmin->two_fa_secret = $secret;

        if (! $this->superAdminService->verifyCode($superAdmin, $validated['code'])) {
            $superAdmin->two_fa_secret = null;

            return new JsonResponse([
                'error' => 'INVALID_2FA_CODE',
                'message' => __('auth.twofa_code_invalid_value'),
            ], 400);
        }

        $superAdmin->save();
        Cache::forget($this->pendingTwoFaSecretCacheKey($superAdmin));

        return new JsonResponse(['status' => 'ok']);
    }

    public function disable2fa(Request $request): JsonResponse
    {
        /** @var array{password: string} $validated */
        $validated = $request->validate([
            'password' => ['required', 'string'],
        ]);

        /** @var SuperAdmin $superAdmin */
        $superAdmin = $request->user('super_admin_api');

        if (! Hash::check($validated['password'], (string) $superAdmin->password_hash)) {
            return new JsonResponse([
                'error' => 'INVALID_PASSWORD',
                'message' => __('auth.password_incorrect'),
            ], 401);
        }

        $superAdmin->two_fa_secret = null;
        $superAdmin->save();

        return new JsonResponse(['status' => 'ok']);
    }

    private function pendingTwoFaSecretCacheKey(SuperAdmin $superAdmin): string
    {
        return '2fa_setup:'.(string) $superAdmin->id;
    }
}
