<?php

declare(strict_types=1);

namespace App\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\CRM\Domain\Models\CrmPipeline;

/**
 * Issue #5711 — Policy du pipeline CRM client (tenant).
 *
 * Le pipeline est un objet de configuration métier : réservé aux managers
 * `principal`, `rh` et `marketing` du tenant. L'isolation tenant est
 * garantie par la comparaison `company_id` (en plus du scope global
 * BelongsToCompany qui rend un pipeline d'un autre tenant déjà introuvable).
 */
class CrmPipelinePolicy
{
    public function viewAny(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh', 'marketing');
    }

    public function view(Employee $actor, CrmPipeline $pipeline): bool
    {
        return $actor->company_id === $pipeline->company_id
            && $actor->hasManagerRole('principal', 'rh', 'marketing');
    }

    public function create(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh', 'marketing');
    }

    public function update(Employee $actor, CrmPipeline $pipeline): bool
    {
        return $actor->company_id === $pipeline->company_id
            && $actor->hasManagerRole('principal', 'rh', 'marketing');
    }

    public function delete(Employee $actor, CrmPipeline $pipeline): bool
    {
        return $actor->company_id === $pipeline->company_id
            && $actor->hasManagerRole('principal', 'rh', 'marketing');
    }
}
