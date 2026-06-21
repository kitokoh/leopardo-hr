<?php

namespace App\Policies;

use App\Models\Partner;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PartnerPolicy
{
    use HandlesAuthorization;

    /**
     * Determine if the user can view his own partner stats.
     */
    public function view(User $user, Partner $partner): bool
    {
        return $user->id === $partner->user_id;
    }

    /**
     * Determine if the user can manage this partner (Super Admin only).
     */
    public function manage(User $user): bool
    {
        // Dans ce système, nous utilisons le guard super_admin_api pour les routes admin,
        // mais nous pouvons ajouter une sécurité supplémentaire ici si nécessaire.
        return true;
    }
}
