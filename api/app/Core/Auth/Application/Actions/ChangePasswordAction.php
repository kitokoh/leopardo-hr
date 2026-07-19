<?php

declare(strict_types=1);

namespace App\Core\Auth\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Exceptions\DomainException;
use Illuminate\Support\Facades\Hash;

/**
 * Use Case : Changement de mot de passe.
 *
 * @throws DomainException  When the current password is incorrect.
 */
final class ChangePasswordAction
{
    /**
     * @return array{token: string, token_type: string, token_expires_at: ?string}|null
     *         A fresh Sanctum token for the current device, or null when the
     *         request was not made through a token guard (e.g. session auth).
     */
    public function execute(Employee $employee, string $currentPassword, string $newPassword): ?array
    {
        if (! Hash::check($currentPassword, $employee->password_hash)) {
            throw new class('Mot de passe actuel incorrect') extends DomainException {
                public function errorCode(): string { return 'INVALID_CURRENT_PASSWORD'; }
                public function statusCode(): int   { return 422; }
            };
        }

        $employee->password_hash = Hash::make($newPassword);
        $employee->save();

        // Revoke every existing Sanctum token. A stolen token must stop
        // working the moment the password is changed instead of staying
        // valid until its natural expiration (up to 7 days). We then issue a
        // fresh token for the current device so the caller isn't logged out
        // by their own password change. See
        // docs/security/AUDIT_API_2026-07-19.md, section 3.
        $currentToken = $employee->currentAccessToken();
        $tokenName = ($currentToken?->name) ?? 'api';
        $abilities = ($currentToken?->abilities) ?? ['*'];
        $expiresAt = $currentToken?->expires_at;

        $employee->tokens()->delete();

        $newToken = $employee->createToken($tokenName, $abilities, $expiresAt);

        return [
            'token' => $newToken->plainTextToken,
            'token_type' => 'Bearer',
            'token_expires_at' => $expiresAt?->toIso8601String(),
        ];
    }
}
