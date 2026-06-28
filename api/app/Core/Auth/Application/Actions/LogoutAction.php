<?php

declare(strict_types=1);

namespace App\Core\Auth\Application\Actions;

use Laravel\Sanctum\Contracts\HasApiTokens;

/**
 * Use Case : Déconnexion — révocation du token courant.
 */
final class LogoutAction
{
    public function execute(HasApiTokens $user): void
    {
        $token = $user->currentAccessToken();
        if ($token) {
            $token->delete();
        }
    }
}
