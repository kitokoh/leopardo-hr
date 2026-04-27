<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SuperAdmin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class PlatformAuthController extends Controller
{
    public function __construct(
        private readonly \App\Services\SuperAdminService $superAdminService,
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
            'secret' => ['required', 'string'],
            'code' => ['required', 'string'],
        ]);

        /** @var SuperAdmin $superAdmin */
        $superAdmin = $request->user('super_admin_api');

        if ($superAdmin->two_fa_secret) {
            return new JsonResponse([
                'error' => 'ALREADY_ENABLED',
                'message' => 'Le 2FA est déjà activé pour ce compte.',
            ], 400);
        }

        // We temporarily set the secret on the model without saving to use the service verification
        $superAdmin->two_fa_secret = $validated['secret'];

        if (! $this->superAdminService->verifyCode($superAdmin, $validated['code'])) {
            return new JsonResponse([
                'error' => 'INVALID_2FA_CODE',
                'message' => 'Le code 2FA fourni est invalide.',
            ], 400);
        }

        $superAdmin->save(); // Save the validated secret

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
                'message' => 'Mot de passe incorrect.',
            ], 401);
        }

        $superAdmin->two_fa_secret = null;
        $superAdmin->save();

        return new JsonResponse(['status' => 'ok']);
    }
}
