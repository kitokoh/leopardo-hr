<?php

namespace App\Policies;

use App\Models\Commission;
use App\Models\User;
use App\Models\Partner;
use Illuminate\Auth\Access\HandlesAuthorization;

class CommissionPolicy
{
    use HandlesAuthorization;

    /**
     * Determine if the user can view the commission.
     */
    public function view(User $user, Commission $commission): bool
    {
        $partner = Partner::where('user_id', $user->id)->first();
        return $partner && $commission->partner_id === $partner->id;
    }
}
