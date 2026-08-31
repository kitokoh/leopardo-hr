<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\TravelAgency\Domain\Models\TravelQuiz;

/**
 * TRAVEL-904 (#6107) — Policy des quiz & jeux-concours.
 * Gestion (CRUD + questions + résultats) : rôles opérationnels de l'agence ;
 * participation : tout employé authentifié du tenant.
 */
final class TravelQuizPolicy
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
        return $actor->hasManagerRole('principal', 'rh', 'manager', 'agent');
    }

    public function update(Employee $actor, TravelQuiz $quiz): bool
    {
        return $this->create($actor) && $quiz->company_id === $actor->company_id;
    }

    public function delete(Employee $actor, TravelQuiz $quiz): bool
    {
        return $this->update($actor, $quiz);
    }

    public function participate(Employee $actor, TravelQuiz $quiz): bool
    {
        return $quiz->company_id === $actor->company_id;
    }

    /**
     * Résultats agrégés : réservés à la direction (travel.manage).
     */
    public function manage(Employee $actor, TravelQuiz $quiz): bool
    {
        return $actor->hasManagerRole('principal', 'rh') && $quiz->company_id === $actor->company_id;
    }
}
