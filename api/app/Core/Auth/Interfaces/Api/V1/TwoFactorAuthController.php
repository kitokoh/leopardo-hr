<?php

declare(strict_types=1);

namespace App\Core\Auth\Interfaces\Api\V1;

use App\Core\Auth\Domain\Exceptions\TwoFactorException;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Auth\Infrastructure\Services\TwoFactorAuthService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * #5436 — 2FA/TOTP des comptes entreprise.
 *
 * Endpoints :
 * - GET  /auth/2fa/status  (auth)         état + politique ;
 * - POST /auth/2fa/enroll  (auth)         secret + QR ;
 * - POST /auth/2fa/confirm (auth)         activation (1er code) + recovery codes ;
 * - POST /auth/2fa/disable (auth)         désactivation (code requis) ;
 * - POST /auth/2fa/recovery-codes (auth)  régénération ;
 * - POST /auth/2fa/verify  (public)       challenge + code → token Sanctum.
 */
class TwoFactorAuthController extends Controller
{
    public function __construct(private readonly TwoFactorAuthService $twoFactor) {}

    public function status(Request $request): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();

        return new JsonResponse(['data' => $this->twoFactor->status($employee)]);
    }

    public function enroll(Request $request): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();

        $result = $this->twoFactor->enroll($employee);

        return new JsonResponse(['data' => $result], 201);
    }

    public function confirm(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:16'],
        ]);

        /** @var Employee $employee */
        $employee = $request->user();

        $result = $this->twoFactor->confirm($employee, (string) $validated['code']);

        return new JsonResponse(['data' => $result], 201);
    }

    public function disable(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:16'],
        ]);

        /** @var Employee $employee */
        $employee = $request->user();

        $this->twoFactor->disable($employee, (string) $validated['code']);

        return new JsonResponse(['data' => ['enabled' => false]]);
    }

    public function recoveryCodes(Request $request): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();

        $codes = $this->twoFactor->regenerateRecoveryCodes($employee);

        return new JsonResponse(['data' => ['recovery_codes' => $codes]], 201);
    }

    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'challenge_token' => ['required', 'string', 'max:128'],
            'code' => ['sometimes', 'string', 'max:16'],
            'recovery_code' => ['sometimes', 'string', 'max:32'],
            'device_name' => ['nullable', 'string', 'max:255'],
            'remember_device' => ['sometimes', 'boolean'],
        ]);

        if ((($validated['code'] ?? null) === null || $validated['code'] === '')
            && (($validated['recovery_code'] ?? null) === null || $validated['recovery_code'] === '')) {
            throw TwoFactorException::invalidCode();
        }

        $result = $this->twoFactor->verifyChallenge(
            (string) $validated['challenge_token'],
            isset($validated['code']) && is_string($validated['code']) ? $validated['code'] : null,
            isset($validated['recovery_code']) && is_string($validated['recovery_code']) ? $validated['recovery_code'] : null,
            isset($validated['device_name']) && is_string($validated['device_name']) ? $validated['device_name'] : null,
        );

        $response = new JsonResponse([
            'data' => [
                'id' => $result['employee']->id,
                'email' => $result['employee']->email,
            ],
            'token' => $result['token'],
            'token_type' => $result['token_type'],
            'token_expires_at' => $result['token_expires_at'],
        ]);

        if (($validated['remember_device'] ?? false) === true) {
            $response->withCookie(
                cookie('mfa_remember_'.$result['employee']->id, $this->twoFactor->rememberCookieValue($result['employee']), 60 * 24 * 30)
            );
        }

        return $response;
    }
}
