<?php

declare(strict_types=1);

namespace App\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Marketing\Domain\Models\SocialAccount;

/**
 * Acces restreint aux managers `principal` ou `marketing`, suivant le
 * pattern TrainingPolicy (hasManagerRole).
 */
class SocialAccountPolicy
{
    public function view(Employee $actor, SocialAccount $account): bool
    {
        return $actor->company_id === $account->company_id
            && $actor->hasManagerRole('principal', 'marketing');
    }

    public function connect(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'marketing');
    }

    public function disconnect(Employee $actor, SocialAccount $account): bool
    {
        return $actor->company_id === $account->company_id
            && $actor->hasManagerRole('principal', 'marketing');
    }
}
