<?php

declare(strict_types=1);

namespace App\Modules\Communication\Domain\Exceptions;

use RuntimeException;

/**
 * La boite n'est plus utilisable pour la sync (R2 #7687) : token expire
 * non rafraichissable (`invalid_grant`), acces revoque cote Google, 401
 * en cours de passe. L'integration a deja ete marquee `error` — le job
 * capture cette exception pour NOTIFIER l'utilisateur (exigence issue :
 * « erreurs/token expire -> status error + notification user ») sans
 * retenter inutilement.
 */
class GmailSyncAuthException extends RuntimeException
{
    public function __construct(string $code = 'gmail_auth_failed')
    {
        parent::__construct($code);
    }
}
