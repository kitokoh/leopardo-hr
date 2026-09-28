<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Application\Actions;

use App\Modules\HealthManager\Domain\Exceptions\HealthResourceInUseException;
use App\Modules\HealthManager\Domain\Models\HealthPractitioner;
use Illuminate\Database\ConnectionInterface;

/**
 * Cas d'usage « supprimer un praticien » — HC-002 (#7786, BC-31).
 *
 * Extrait de `HealthPractitionerController::destroy` (BOS-024b, #8213) :
 * suppression bloquée si le praticien porte une activité clinique
 * (rendez-vous ou consultations) → 422 HEALTH_RESOURCE_IN_USE ; sinon
 * pivot spécialités + fiche supprimés en transaction.
 */
final class DeleteHealthPractitionerAction
{
    public function __construct(private readonly ConnectionInterface $db) {}

    public function execute(HealthPractitioner $practitioner): void
    {
        // Suppression bloquée si le praticien porte une activité clinique
        // (rendez-vous ou consultations) — 422 HEALTH_RESOURCE_IN_USE.
        if ($practitioner->appointments()->exists() || $practitioner->consultations()->exists()) {
            throw new HealthResourceInUseException;
        }

        $this->db->transaction(function () use ($practitioner): void {
            $practitioner->practitionerSpecialties()->delete();
            $practitioner->delete();
        });
    }
}
