<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\HealthManager\Domain\Access\HealthAccess;
use App\Modules\HealthManager\Domain\Models\HealthInvoiceItem;

/**
 * #7791 (BC-30) — Policy des lignes de facture de soins.
 *
 * Deny-by-default (spec §2) : même périmètre que la facture parente —
 * direction et facturation gèrent, réception en LECTURE, praticiens et
 * employé lambda refusés. Cross-tenant → refus (fail-closed).
 */
class HealthInvoiceItemPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return HealthAccess::isAdmin($actor)
            || HealthAccess::isBilling($actor)
            || HealthAccess::isReception($actor);
    }

    public function view(Employee $actor, HealthInvoiceItem $item): bool
    {
        return $item->company_id === $actor->company_id && $this->viewAny($actor);
    }

    public function create(Employee $actor): bool
    {
        return HealthAccess::isAdmin($actor) || HealthAccess::isBilling($actor);
    }

    public function update(Employee $actor, HealthInvoiceItem $item): bool
    {
        return $item->company_id === $actor->company_id
            && (HealthAccess::isAdmin($actor) || HealthAccess::isBilling($actor));
    }

    public function delete(Employee $actor, HealthInvoiceItem $item): bool
    {
        return $this->update($actor, $item);
    }
}
