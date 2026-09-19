<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\HealthManager\Domain\Access\HealthAccess;
use App\Modules\HealthManager\Domain\Models\HealthPrescription;

/**
 * HC-005 (#7789) — Policy des ordonnances (BC-30), deny-by-default.
 *
 * CONTENU MÉDICAL : praticiens actifs et direction lisent l'historique ;
 * la création est bornée au praticien AUTEUR de la consultation (ou à la
 * direction) côté contrôleur ; réception et facturation → 403.
 */
class HealthPrescriptionPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return HealthAccess::canViewMedicalRecords($actor);
    }

    public function view(Employee $actor, HealthPrescription $prescription): bool
    {
        return $this->viewAny($actor) && $prescription->company_id === $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return HealthAccess::canViewMedicalRecords($actor);
    }
}
