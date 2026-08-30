<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\TravelAgency\Domain\Models\TravelArticle;

/**
 * TRAVEL-901 (#6104) — Policy des articles éditoriaux.
 * Écriture/modération : principal, rh, manager, agent ; lecture : tenant.
 */
class TravelArticlePolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, TravelArticle $article): bool
    {
        return $article->company_id === $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh', 'manager', 'agent');
    }

    public function update(Employee $actor, TravelArticle $article): bool
    {
        return $this->create($actor) && $article->company_id === $actor->company_id;
    }

    public function delete(Employee $actor, TravelArticle $article): bool
    {
        return $this->update($actor, $article);
    }
}
