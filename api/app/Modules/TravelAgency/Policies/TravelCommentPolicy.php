<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\TravelAgency\Domain\Models\TravelComment;

/**
 * TRAVEL-902 (#6105) — Policy des commentaires.
 * Lecture : tenant ; écriture : rôles opérationnels ; modération :
 * principal/rh/manager.
 */
class TravelCommentPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, TravelComment $comment): bool
    {
        return $comment->company_id === $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh', 'manager', 'agent', 'checkin');
    }

    public function update(Employee $actor, TravelComment $comment): bool
    {
        return $this->create($actor) && $comment->company_id === $actor->company_id;
    }

    public function moderate(Employee $actor, TravelComment $comment): bool
    {
        return $comment->company_id === $actor->company_id
            && $actor->hasManagerRole('principal', 'rh', 'manager');
    }
}
