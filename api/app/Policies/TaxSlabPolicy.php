<?php

declare(strict_types=1);

namespace App\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Modules\Payroll\Domain\Models\TaxSlab;

/**
 * Issue #1917 — Policy Laravel pour les barèmes fiscaux (tax_slabs).
 *
 * Remplace les checks inline `isManager()`/`abort(403)` de
 * `TaxSlabController` (CONVENTIONS §2.5 : chaque action controller passe
 * par une Policy). Matrice :
 *   - SuperAdmin (plateforme) : tout (gestion nationale) ;
 *   - Manager/principal RH du tenant : CRUD sur les barèmes de SA société ;
 *   - autres rôles / autre tenant : refus.
 */
class TaxSlabPolicy
{
    public function viewAny(Employee|SuperAdmin $actor): bool
    {
        if ($actor instanceof SuperAdmin) {
            return true;
        }

        return $actor->isManager();
    }

    public function view(Employee|SuperAdmin $actor, TaxSlab $taxSlab): bool
    {
        if ($actor instanceof SuperAdmin) {
            return true;
        }

        return $actor->isManager() && $taxSlab->company_id === $actor->company_id;
    }

    public function create(Employee|SuperAdmin $actor): bool
    {
        if ($actor instanceof SuperAdmin) {
            return true;
        }

        return $actor->isManager();
    }

    public function update(Employee|SuperAdmin $actor, TaxSlab $taxSlab): bool
    {
        if ($actor instanceof SuperAdmin) {
            return true;
        }

        return $actor->isManager() && $taxSlab->company_id === $actor->company_id;
    }

    public function delete(Employee|SuperAdmin $actor, TaxSlab $taxSlab): bool
    {
        if ($actor instanceof SuperAdmin) {
            return true;
        }

        return $actor->isManager() && $taxSlab->company_id === $actor->company_id;
    }

    /**
     * Soumission au workflow de validation (#1813) — manager du tenant.
     */
    public function submit(Employee|SuperAdmin $actor, TaxSlab $taxSlab): bool
    {
        return $this->update($actor, $taxSlab);
    }
}
