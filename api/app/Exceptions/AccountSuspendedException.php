<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Issue #2618 — compte suspendu : le login (email ou Google) est refusé
 * tant que `status !== 'active'` (fail-closed sur les comptes suspendus).
 */
class AccountSuspendedException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Ce compte est suspendu. Contactez le support.', 403, 'ACCOUNT_SUSPENDED');
    }
}
