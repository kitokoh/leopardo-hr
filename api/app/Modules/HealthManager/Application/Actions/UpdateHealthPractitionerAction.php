<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Application\Actions;

use App\Modules\HealthManager\Domain\Models\HealthPractitioner;
use Illuminate\Database\ConnectionInterface;

/**
 * Cas d'usage « mettre à jour un praticien » — HC-002 (#7786, BC-31).
 *
 * Extrait de `HealthPractitionerController::update` (BOS-024b, #8213) :
 * mise à jour en transaction ; les spécialités ne sont re-synchronisées
 * que si `specialty_ids` est présent dans le payload (source unique :
 * `RegisterHealthPractitionerAction::syncSpecialties()`).
 */
final class UpdateHealthPractitionerAction
{
    public function __construct(private readonly ConnectionInterface $db) {}

    /**
     * @param  array<string, mixed>  $validated  Payload validé (UpdateHealthPractitionerRequest).
     */
    public function execute(HealthPractitioner $practitioner, string $companyId, array $validated): HealthPractitioner
    {
        $syncSpecialties = array_key_exists('specialty_ids', $validated);
        /** @var list<int|string> $specialtyIds */
        $specialtyIds = $validated['specialty_ids'] ?? [];
        unset($validated['specialty_ids']);

        $this->db->transaction(function () use ($practitioner, $companyId, $validated, $syncSpecialties, $specialtyIds): void {
            $practitioner->update($validated);

            if ($syncSpecialties) {
                RegisterHealthPractitionerAction::syncSpecialties($practitioner, $specialtyIds, $companyId);
            }
        });

        return $practitioner->refresh();
    }
}
