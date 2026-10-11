<?php

declare(strict_types=1);

namespace App\Modules\Fundraising\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Fundraising\Domain\Models\FundraisingPayout;

/**
 * RBAC des reversements (verticale FUNDRAISING — spec §3.3) : argent
 * sortant ⇒ gestion strictement réservée au responsable du tenant
 * (`principal`/`rh`, company_id vérifié), lecture ouverte aux membres.
 * deny-by-default.
 */
class FundraisingPayoutPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, FundraisingPayout $payout): bool
    {
        return (string) $payout->company_id === (string) $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh');
    }

    public function update(Employee $actor, FundraisingPayout $payout): bool
    {
        return $actor->hasManagerRole('principal', 'rh')
            && (string) $payout->company_id === (string) $actor->company_id;
    }
}
