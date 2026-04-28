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

    /**
     * Initialize 2FA setup for Super-Admin.
     * Generates a secret and returns QR code URL.
     * The secret is stored in cache temporarily for verification.
     */
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

        // Generate a new secret server-side
        $secret = $this->superAdminService->generateSecret();
        
        // Store the secret temporarily in cache (10 minutes for user to scan QR and verify)
        $cacheKey = '2fa_setup_' . $superAdmin->id;
        cache()->put($cacheKey, $secret, now()->addMinutes(10));

        // Get QR code URL for the user to scan
        $qrCodeUrl = $this->superAdminService->getQrCodeUrl($superAdmin, $secret);

        return new JsonResponse([
            'secret' => $secret,
            'qr_code_url' => $qrCodeUrl,
            'message' => 'Scannez le QR code avec votre application d\'authentification, puis soumettez le code généré.',
        ]);
    }

    /**
     * Enable 2FA for Super-Admin after verifying the code.
     * Uses the secret stored in cache from setup2fa step.
     */
    public function enable2fa(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        /** @var SuperAdmin $superAdmin */
        $superAdmin = $request->user('super_admin_api');

        if ($superAdmin->two_fa_secret) {
            return new JsonResponse([
                'error' => 'ALREADY_ENABLED',
                'message' => 'Le 2FA est déjà activé pour ce compte.',
            ], 400);
        }

        // Retrieve the secret from cache (set by setup2fa)
        $cacheKey = '2fa_setup_' . $superAdmin->id;
        $secret = cache()->get($cacheKey);

        if (!$secret) {
            return new JsonResponse([
                'error' => 'SETUP_REQUIRED',
                'message' => 'Veuillez d\'abord appeler setup2fa pour générer un secret.',
            ], 400);
        }

        // Temporarily set the secret on the model for verification (without saving)
        $superAdmin->two_fa_secret = $secret;

        if (! $this->superAdminService->verifyCode($superAdmin, $validated['code'])) {
            // Clear the cached secret on failed verification
            cache()->forget($cacheKey);
            
            return new JsonResponse([
                'error' => 'INVALID_2FA_CODE',
                'message' => 'Le code 2FA fourni est invalide.',
            ], 400);
        }

        // Verification successful - persist the secret to database
        $superAdmin->two_fa_secret = $secret;
        $superAdmin->save();

        // Clear the cached secret
        cache()->forget($cacheKey);

        return new JsonResponse([
            'status' => 'ok',
            'message' => 'Le 2FA a été activé avec succès.',
        ]);
    }

    /**
     * Disable 2FA for Super-Admin.
     * Requires password confirmation for security.
     */
    public function disable2fa(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string'],
        ]);

        /** @var SuperAdmin $superAdmin */
        $superAdmin = $request->user('super_admin_api');

        if (!$superAdmin->two_fa_secret) {
            return new JsonResponse([
                'error' => 'NOT_ENABLED',
                'message' => 'Le 2FA n\'est pas activé pour ce compte.',
            ], 400);
        }

        if (!\Hash::check($validated['password'], $superAdmin->password_hash)) {
            return new JsonResponse([
                'error' => 'INVALID_PASSWORD',
                'message' => 'Le mot de passe fourni est incorrect.',
            ], 401);
        }

        $superAdmin->two_fa_secret = null;
        $superAdmin->save();

        return new JsonResponse([
            'status' => 'ok',
            'message' => 'Le 2FA a été désactivé avec succès.',
        ]);
    }
}
