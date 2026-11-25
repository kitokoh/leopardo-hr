<?php

declare(strict_types=1);

namespace App\Core\Auth\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Auth\Infrastructure\Services\TokenExpiryService;
use App\Exceptions\DomainException;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Use Case : Changement de mot de passe.
 *
 * @throws DomainException When the current password is incorrect.
 */
final class ChangePasswordAction
{
    /**
     * @return array{token: string, token_type: string, token_expires_at: ?string} A fresh Sanctum token for the current device.
     */
    public function execute(Employee $employee, string $currentPassword, string $newPassword): array
    {
        // #9922 — `password_hash` est declare `string|null` sur le modele
        // (colonne nullable en base : un employe invite n'a pas encore de mot
        // de passe). `Hash::check()` exige un `string`. On traite explicitement
        // le cas « pas encore de mot de passe » comme un echec de verification,
        // au lieu de laisser PHPStan signaler un `string|null` transmis a un
        // parametre `string` — et au lieu de caster en `(string)`, ce qui
        // aurait transforme un `null` en chaine vide et fait passer un
        // `Hash::check('', '')` silencieux.
        $storedHash = $employee->password_hash;

        if ($storedHash === null || ! Hash::check($currentPassword, $storedHash)) {
            throw new class('Mot de passe actuel incorrect') extends DomainException
            {
                public function errorCode(): string
                {
                    return 'INVALID_CURRENT_PASSWORD';
                }

                public function statusCode(): int
                {
                    return 422;
                }
            };
        }

        $employee->password_hash = Hash::make($newPassword);
        $employee->save();

        // Revoke every existing Sanctum token. A stolen token must stop
        // working the moment the password is changed instead of staying
        // valid until its natural expiration. We then issue a fresh token for
        // the current device so the caller isn't logged out by their own
        // password change. See
        // docs/security/AUDIT_API_2026-07-19.md, section 3.
        $currentToken = $employee->currentAccessToken();
        // #9898 — `currentAccessToken()` est declare `PersonalAccessToken`
        // (non nullable) par Sanctum, mais renvoie `null` hors middleware
        // `auth:sanctum`. On annote la variable pour que le `??` reste
        // justifie aux yeux de PHPStan.
        /** @var PersonalAccessToken|null $currentToken */
        $tokenName = $currentToken->name ?? 'api';
        $abilities = $currentToken->abilities ?? ['*'];
        // #9344 (audit sécurité 2026-11-10, F2) — on ne réhérite PAS de
        // l'échéance du jeton courant : un jeton émis avant le durcissement
        // (30 j) aurait prolongé la fenêtre d'exposition. On réémet avec la
        // durée courante (12 h).
        $expiresAt = app(TokenExpiryService::class)->forInteractive();

        $employee->tokens()->delete();

        $newToken = $employee->createToken($tokenName, $abilities, $expiresAt);

        return [
            'token' => $newToken->plainTextToken,
            'token_type' => 'Bearer',
            'token_expires_at' => $expiresAt?->toIso8601String(),
        ];
    }
}
