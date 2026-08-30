<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\TravelAgency\Domain\Models\TravelQuiz;

/**
 * TRAVEL-904 (#6107) — Policy des quiz & jeux-concours.
 * Lecture/participation : tenant ; gestion (CRUD, résultats) :
 * principal/rh/manager.
 */
class TravelQuizPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, TravelQuiz $quiz): bool
    {
        return $quiz->company_id === $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh', 'manager');
    }

    public function update(Employee $actor, TravelQuiz $quiz): bool
    {
        return $this->create($actor) && $quiz->company_id === $actor->company_id;
    }

    /** Participation : tout employé authentifié du tenant. */
    public function participate(Employee $actor, TravelQuiz $quiz): bool
    {
        return $quiz->company_id === $actor->company_id;
    }

    /** Résultats (gestion) : mêmes rôles que l'écriture. */
    public function viewResults(Employee $actor, TravelQuiz $quiz): bool
    {
        return $this->update($actor, $quiz);
    }
}
