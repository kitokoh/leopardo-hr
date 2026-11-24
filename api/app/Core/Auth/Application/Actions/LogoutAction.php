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
        /** @var PersonalAccessToken|null $token */
        $token = $user->currentAccessToken();

        // #9898 — `currentAccessToken()` est declare `TToken` (donc
        // `PersonalAccessToken`, non nullable) mais renvoie `null` quand
        // aucun jeton n'est attache (appel hors middleware auth:sanctum).
        // Le `@var ...|null` ci-dessus dit la verite ; le test `!== null`
        // n'est donc plus tautologique.
        if ($token !== null) {
            $token->delete();
        }

        // #5439 — journal d'audit global : révocation de jeton (auth).
        if ($user instanceof Employee && $token !== null) {
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
