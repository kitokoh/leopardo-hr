<?php

declare(strict_types=1);

namespace App\Modules\CRM\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\CRM\Domain\Models\CrmPipelineStage;

/**
 * Issue #5711 — Policy des stages de pipeline CRM client (tenant).
 *
 * Les stages sont gérés avec le pipeline parent : réservés aux managers
 * `principal`, `rh`, `marketing`. Le rattachement d'un stage au pipeline
 * d'un autre tenant est déjà impossible au niveau base (FK composite) et
 * re-vérifié ici par `company_id`.
 */
class CrmPipelineStagePolicy
{
    public function viewAny(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh', 'marketing');
    }

    public function view(Employee $actor, CrmPipelineStage $stage): bool
    {
        return $actor->company_id === $stage->company_id
            && $actor->hasManagerRole('principal', 'rh', 'marketing');
    }

    public function create(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh', 'marketing');
    }

    public function update(Employee $actor, CrmPipelineStage $stage): bool
    {
        return $actor->company_id === $stage->company_id
            && $actor->hasManagerRole('principal', 'rh', 'marketing');
    }

    public function delete(Employee $actor, CrmPipelineStage $stage): bool
    {
        return $actor->company_id === $stage->company_id
            && $actor->hasManagerRole('principal', 'rh', 'marketing');
    }
}
