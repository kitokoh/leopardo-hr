<?php

declare(strict_types=1);

namespace App\Policies;

use App\Core\Auth\Domain\Models\Employee;

/**
 * Policy du canal email CRM — Issue #5726.
 *
 * Les envois (transactionnel et marketing) sont réservés aux rôles
 * `principal` / `marketing` — jamais de garde inline (constitution §V).
 * Liée au modèle marqueur `CrmEmailSuppression` pour l'enregistrement Gate.
 */
class CrmEmailPolicy
{
    public function sendTransactional(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'marketing');
    }

    public function sendMarketing(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'marketing');
    }
}
