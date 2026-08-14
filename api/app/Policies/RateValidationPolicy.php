<?php

declare(strict_types=1);

namespace App\Policies;

use App\Core\Tenant\Domain\Models\SuperAdmin;

/**
 * Issue #1917 — Policy Laravel pour le workflow de validation des taux
 * légaux (#1813). Réservé au SuperAdmin plateforme (double signature).
 *
 * Remplace `assertPlatformAdmin()` inline de `RateValidationAdminController`.
 */
class RateValidationPolicy
{
    public function pending(SuperAdmin $actor): bool
    {
        return true;
    }

    public function approve(SuperAdmin $actor): bool
    {
        return true;
    }

    public function reject(SuperAdmin $actor): bool
    {
        return true;
    }
}
