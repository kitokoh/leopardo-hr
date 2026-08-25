<?php

declare(strict_types=1);

namespace App\Core\Auth\Application\Actions;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use Laravel\Sanctum\Contracts\HasApiTokens;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Use Case : Déconnexion — révocation du token courant.
 */
final class LogoutAction
{
    public function execute(HasApiTokens $user): void
    {
        /** @var PersonalAccessToken $token */
        $token = $user->currentAccessToken();
        if ($token) {
            $token->delete();
        }

        // #5439 — journal d'audit global : révocation de jeton (auth).
        if ($user instanceof Employee) {
            AuditLog::record(
                'auth',
                'auth.token.revoked',
                null,
                $user,
                [],
                ['token_id' => $token->id, 'token_name' => $token->name],
            );
        }
    }
}
