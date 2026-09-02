<?php

declare(strict_types=1);

namespace App\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\CRM\Domain\Models\CrmConsent;

/**
 * Policy des consentements CRM — Issue #5722.
 *
 * Le CRM client appartient aux espaces client (API tenant) : les écritures
 * sont réservées aux rôles `principal` / `marketing`, la lecture à tout
 * manager du tenant courant. Aucune garde inline ne remplace cette policy
 * (constitution §V).
 */
class CrmConsentPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function view(Employee $actor, CrmConsent $consent): bool
    {
        // Fail-closed cross-tenant (#3232) : jamais visible hors tenant.
        if ($consent->company_id !== $actor->company_id) {
            return false;
        }

        return $actor->isManager();
    }

    public function create(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'marketing');
    }

    public function revoke(Employee $actor, CrmConsent $consent): bool
    {
        if ($consent->company_id !== $actor->company_id) {
            return false;
        }

        return $actor->hasManagerRole('principal', 'marketing');
    }
}
