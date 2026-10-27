<?php

declare(strict_types=1);

namespace App\Modules\HospitalityManager\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\HospitalityManager\Domain\Models\HospitalityRentPayment;
use App\Modules\HospitalityManager\Domain\Policies\Concerns\ChecksHospitalityPropertyAccess;

/**
 * HOSP-005 (#7947, BC-32) — Policy des échéances de loyer.
 *
 * L'échéance hérite de l'autorisation de l'établissement. L'encaissement
 * (`record`) est une opération de GESTION : un employé en lecture seule
 * ne peut pas enregistrer un paiement, même sur son propre établissement.
 */
class HospitalityRentPaymentPolicy
{
    use ChecksHospitalityPropertyAccess;

    public function viewAny(Employee $actor): bool
    {
        return $this->canViewPropertyResource($actor, null);
    }

    public function view(Employee $actor, HospitalityRentPayment $payment): bool
    {
        return $payment->company_id === $actor->company_id
            && $this->canViewPropertyResource($actor, $payment->getAttribute('property_id'));
    }

    public function record(Employee $actor, HospitalityRentPayment $payment): bool
    {
        return $payment->company_id === $actor->company_id
            && $this->canManagePropertyResource($actor, $payment->getAttribute('property_id'));
    }
}
