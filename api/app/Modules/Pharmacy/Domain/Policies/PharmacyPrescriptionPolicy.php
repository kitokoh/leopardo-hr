<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Pharmacy\Domain\Models\PharmacyPrescription;

/**
 * RBAC ordonnances et ordonnancier — PHARMA-006 (#7803).
 *
 * Les ordonnances portent des PII santé : saisie par tout employé du
 * comptoir (délivrance), mais l'ORDONNANCIER des produits contrôlés
 * (`viewRegister`) est réservé au manager (pharmacy.compliance).
 */
class PharmacyPrescriptionPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, PharmacyPrescription $prescription): bool
    {
        return $prescription->company_id === (string) $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return true;
    }

    public function update(Employee $actor, PharmacyPrescription $prescription): bool
    {
        return $actor->isManager() && $prescription->company_id === (string) $actor->company_id;
    }

    public function viewRegister(Employee $actor): bool
    {
        return $actor->isManager();
    }
}
