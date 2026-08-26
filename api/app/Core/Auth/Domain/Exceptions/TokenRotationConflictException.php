<?php

declare(strict_types=1);

namespace App\Core\Auth\Domain\Exceptions;

use App\Exceptions\DomainException;

/**
 * #5581 — Rotation de token non atomique : deux requêtes concurrentes ont
 * tenté de faire tourner le MÊME token (fenêtre de rafraîchissement).
 *
 * La transaction + verrou pessimiste (`SELECT ... FOR UPDATE`) de
 * `RefreshTokenAction`/`TokenAutoRefreshMiddleware` sérialise les rotations :
 * la première gagne (ancien token supprimé, nouveau token émis), les
 * suivantes trouvent la ligne supprimée et échouent ici. Le client doit
 * utiliser le token renvoyé par la requête concurrente (ou se ré-authentifier).
 *
 * Code d'erreur rendu par le handler (message i18n côté client) :
 * - TOKEN_ALREADY_ROTATED (409).
 */
final class TokenRotationConflictException extends DomainException
{
    public static function alreadyRotated(): self
    {
        return new self('The access token has already been rotated by a concurrent request', 409, 'TOKEN_ALREADY_ROTATED');
    }
}
