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
    public function __construct(
        private readonly SuperAdminService $superAdminService,
    ) {}

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
            'two_fa_code' => ['nullable', 'string'],
        ]);

        /** @var SuperAdmin|null $superAdmin */
        $superAdmin = SuperAdmin::query()->where('email', $validated['email'])->first();

        if (! $superAdmin || ! Hash::check($validated['password'], $superAdmin->password_hash)) {
            return new JsonResponse([
                'error' => 'INVALID_CREDENTIALS',
                'message' => 'INVALID_CREDENTIALS',
                'localized_message' => __('errors.INVALID_CREDENTIALS'),
            ], 401);
        }

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
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'email' => ['sometimes', 'email', 'max:150'],
        ]);

        /** @var SuperAdmin $superAdmin */
        $superAdmin = $request->user('super_admin_api');

        if (isset($validated['email']) && $validated['email'] !== $superAdmin->email) {
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
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            // Issue #5620 : min 8 caractères + au moins 1 chiffre.
            'new_password' => ['required', 'string', Password::min(8)->numbers(), 'max:255', 'confirmed'],
        ]);

        /** @var SuperAdmin $superAdmin */
        $superAdmin = $request->user('super_admin_api');

        if (! Hash::check($validated['current_password'], $superAdmin->password_hash)) {
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
        $validated = $request->validate([
            'password' => ['required', 'string'],
        ]);

        /** @var SuperAdmin $superAdmin */
        $superAdmin = $request->user('super_admin_api');

        if (! Hash::check($validated['password'], $superAdmin->password_hash)) {
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
