<?php

declare(strict_types=1);

namespace App\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\CRM\Domain\Models\CrmActivity;

/**
 * Issue #5711 — Policy de la timeline CRM client (tenant).
 *
 * La timeline est un journal append-only : seuls les managers
 * `principal`/`rh`/`marketing` écrivent. En lecture, l'auteur
 * (`created_by`) peut retrouver ses propres entrées.
 */
class CrmActivityPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh', 'marketing');
    }

    public function view(Employee $actor, CrmActivity $activity): bool
    {
        return $actor->company_id === $activity->company_id
            && ($actor->hasManagerRole('principal', 'rh', 'marketing') || $activity->created_by === $actor->id);
    }

    public function create(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh', 'marketing');
    }
}
