<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\HealthManager\Domain\Access\HealthAccess;
use App\Modules\HealthManager\Domain\Models\HealthInvoice;

/**
 * #7791 (BC-30) — Policy des factures de soins.
 *
 * Deny-by-default (spec §2) : direction et facturation gèrent ; réception
 * en LECTURE (suivi administratif, jamais le contenu médical) ; praticiens
 * et employé lambda refusés. Cross-tenant → refus (fail-closed).
 */
class HealthInvoicePolicy
{
    public function viewAny(Employee $actor): bool
    {
        return HealthAccess::isAdmin($actor)
            || HealthAccess::isBilling($actor)
            || HealthAccess::isReception($actor);
    }

    public function view(Employee $actor, HealthInvoice $invoice): bool
    {
        return $invoice->company_id === $actor->company_id && $this->viewAny($actor);
    }

    public function create(Employee $actor): bool
    {
        return HealthAccess::isAdmin($actor) || HealthAccess::isBilling($actor);
    }

    public function update(Employee $actor, HealthInvoice $invoice): bool
    {
        return $invoice->company_id === $actor->company_id
            && (HealthAccess::isAdmin($actor) || HealthAccess::isBilling($actor));
    }

    public function delete(Employee $actor, HealthInvoice $invoice): bool
    {
        // Une facture émise n'est JAMAIS supprimée physiquement (spec §3) —
        // même périmètre que la mise à jour (annulation côté service).
        return $this->update($actor, $invoice);
    }
}
