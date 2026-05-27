<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SuperAdmin;
use App\Services\SuperAdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use App\Http\Requests\Api\V1\Platform\Disable2faPlatformAuthRequest;
use App\Http\Requests\Api\V1\Platform\Enable2faPlatformAuthRequest;
use App\Http\Requests\Api\V1\Platform\LoginPlatformAuthRequest;

class PlatformAuthController extends Controller
{
    public function __construct(
        private readonly SuperAdminService $superAdminService,
    ) {}

    public function login(LoginPlatformAuthRequest $request): JsonResponse
    {
        $validated = $request->validated();

        /** @var SuperAdmin|null $superAdmin */
        $superAdmin = SuperAdmin::query()->where('email', $validated['email'])->first();

        if (! $superAdmin || ! Hash::check($validated['password'], $superAdmin->password_hash)) {
            return new JsonResponse([
                'error' => 'INVALID_CREDENTIALS',
                'message' => 'INVALID_CREDENTIALS',
                'localized_message' => __('errors.INVALID_CREDENTIALS'),
            ], 401);
        }

        // Check 2FA if enabled
        if ($superAdmin->two_fa_secret) {
            if (! isset($validated['two_fa_code'])) {
                return new JsonResponse([
                    'error' => 'TWO_FA_REQUIRED',
                    'message' => 'Un code 2FA est requis pour ce compte.',
                ], 202); // 202 Accepted but further action needed
            }

            if (! $this->superAdminService->verifyCode($superAdmin, $validated['two_fa_code'])) {
                return new JsonResponse([
                    'error' => 'INVALID_2FA_CODE',
                    'message' => 'Le code 2FA est invalide ou a expire.',
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

    public function setup2fa(Request $request): JsonResponse
    {
        /** @var SuperAdmin $superAdmin */
        $superAdmin = $request->user('super_admin_api');

        if ($superAdmin->two_fa_secret) {
            return new JsonResponse([
                'error' => 'ALREADY_ENABLED',
                'message' => 'Le 2FA est déjà activé pour ce compte.',
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

    public function enable2fa(Enable2faPlatformAuthRequest $request): JsonResponse
    {
        $validated = $request->validated();

        /** @var SuperAdmin $superAdmin */
        $superAdmin = $request->user('super_admin_api');

        if ($superAdmin->two_fa_secret) {
            return new JsonResponse([
                'error' => 'ALREADY_ENABLED',
                'message' => 'Le 2FA est déjà activé pour ce compte.',
            ], 400);
        }

        /** @var string|null $secret */
        $secret = Cache::get($this->pendingTwoFaSecretCacheKey($superAdmin));

        if (! $secret) {
            return new JsonResponse([
                'error' => 'SETUP_REQUIRED',
                'message' => 'Veuillez d\'abord appeler setup2fa pour générer un secret.',
            ], 400);
        }

        $superAdmin->two_fa_secret = $secret;

        if (! $this->superAdminService->verifyCode($superAdmin, $validated['code'])) {
            $superAdmin->two_fa_secret = null;

            return new JsonResponse([
                'error' => 'INVALID_2FA_CODE',
                'message' => 'Le code 2FA fourni est invalide.',
            ], 400);
        }

        $superAdmin->save();
        Cache::forget($this->pendingTwoFaSecretCacheKey($superAdmin));

        return new JsonResponse(['status' => 'ok']);
    }

    public function disable2fa(Disable2faPlatformAuthRequest $request): JsonResponse
    {
        $validated = $request->validated();

        /** @var SuperAdmin $superAdmin */
        $superAdmin = $request->user('super_admin_api');

        if (! Hash::check($validated['password'], $superAdmin->password_hash)) {
            return new JsonResponse([
                'error' => 'INVALID_PASSWORD',
                'message' => 'Mot de passe incorrect.',
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
