<?php

declare(strict_types=1);

namespace App\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\CRM\Domain\Models\CrmSegment;

/**
 * Policy des segments CRM — Issue #5723.
 *
 * Lecture : tout manager du tenant ; écritures (création, mise à jour,
 * suppression, rebuild) : `principal` / `marketing`. Aucune garde inline
 * (constitution §V) ; fail-closed cross-tenant (#3232).
 */
class CrmSegmentPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function view(Employee $actor, CrmSegment $segment): bool
    {
        if ($segment->company_id !== $actor->company_id) {
            return false;
        }

        return $actor->isManager();
    }

    public function create(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'marketing');
    }

    public function update(Employee $actor, CrmSegment $segment): bool
    {
        if ($segment->company_id !== $actor->company_id) {
            return false;
        }

        return $actor->hasManagerRole('principal', 'marketing');
    }

    public function delete(Employee $actor, CrmSegment $segment): bool
    {
        if ($segment->company_id !== $actor->company_id) {
            return false;
        }

        return $actor->hasManagerRole('principal', 'marketing');
    }

    public function rebuild(Employee $actor, CrmSegment $segment): bool
    {
        if ($segment->company_id !== $actor->company_id) {
            return false;
        }

        return $actor->hasManagerRole('principal', 'marketing');
    }
}
