<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\HealthManager\Domain\Access\HealthAccess;
use App\Modules\HealthManager\Domain\Models\HealthInvoice;

/**
 * HC-007 (#7791) — Policy des factures de soins (BC-30), deny-by-default.
 *
 * `health.billing` (comptable) et `health.admin` (direction) gèrent la
 * facturation de bout en bout (brouillon, émission, paiements, annulation,
 * stats) — critère d'acceptation HC-007. Réception, praticiens et employé
 * lambda : 403 partout.
 */
class HealthInvoicePolicy
{
    public function viewAny(Employee $actor): bool
    {
        return HealthAccess::canManageBilling($actor);
    }

    public function view(Employee $actor, HealthInvoice $invoice): bool
    {
        return $this->viewAny($actor) && $invoice->company_id === $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return HealthAccess::canManageBilling($actor);
    }

    public function update(Employee $actor, HealthInvoice $invoice): bool
    {
        return $this->create($actor) && $invoice->company_id === $actor->company_id;
    }

    /**
     * Émission, annulation et encaissement : mêmes gestionnaires.
     */
    public function transition(Employee $actor, HealthInvoice $invoice): bool
    {
        return $this->update($actor, $invoice);
    }
}
