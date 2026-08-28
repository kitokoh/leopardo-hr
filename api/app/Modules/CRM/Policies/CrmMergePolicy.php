<?php

declare(strict_types=1);

namespace App\Modules\CRM\Policies;

use App\Core\Auth\Domain\Models\Employee;

/**
 * #5718 — Policy de la déduplication/fusion CRM.
 *
 * Permission ÉLEVÉE pour la fusion : seul `principal` fusionne (action
 * irréversible côté données — le perdant est archivé mais l'acte est
 * sensible). Les suggestions et la preview restent ouvertes à principal/rh.
 */
class CrmMergePolicy
{
    public function viewSuggestions(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh');
    }

    public function preview(Employee $actor): bool
    {
        return $this->viewSuggestions($actor);
    }

    public function merge(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal');
    }
}
