<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\HealthManager\Domain\Access\HealthAccess;
use App\Modules\HealthManager\Domain\Models\HealthConsultation;
use App\Modules\HealthManager\Domain\Models\HealthPrescription;
use App\Modules\HealthManager\Domain\Models\HealthPrescriptionItem;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Validation\ValidationException;

/**
 * Cas d'usage « créer une ordonnance » — HC-005 (#7789, BC-31).
 *
 * Extrait de `HealthPrescriptionController::store` (BOS-024b, #8213) :
 * consultation du MÊME tenant (404 fail-closed) ; un praticien (non
 * direction) ne prescrit que sur SES consultations (422) ; patient et
 * praticien DÉRIVÉS de la consultation (jamais fournis par le client) ;
 * création ordonnance + lignes en transaction (atomique).
 */
final class CreateHealthPrescriptionAction
{
    public function __construct(private readonly ConnectionInterface $db) {}

    /**
     * @param  array<string, mixed>  $validated  Payload validé (StoreHealthPrescriptionRequest).
     */
    public function execute(Employee $actor, array $validated): HealthPrescription
    {
        // Consultation du MÊME tenant (cross-tenant → 404 fail-closed).
        /** @var HealthConsultation $consultation */
        $consultation = HealthConsultation::query()
            ->where('company_id', $actor->company_id)
            ->whereKey((int) $validated['consultation_id'])
            ->firstOrFail();

        // Un praticien (non direction) ne prescrit que sur SES consultations.
        if (! HealthAccess::isAdmin($actor)
            && $consultation->practitioner_id !== HealthAccess::practitionerId($actor)
        ) {
            // Message rendu à l'identique (clé JSON = texte source) —
            // déplacé tel quel depuis le controller (garde i18n #5432).
            throw ValidationException::withMessages([
                'consultation_id' => [__('Seul le praticien de la consultation peut prescrire.')],
            ]);
        }

        return $this->db->transaction(function () use ($actor, $consultation, $validated): HealthPrescription {
            /** @var HealthPrescription $prescription */
            $prescription = HealthPrescription::query()->create([
                'company_id' => $actor->company_id,
                'consultation_id' => (int) $consultation->getAttribute('id'),
                // Dérivés de la consultation — jamais fournis par le client.
                'patient_id' => $consultation->patient_id,
                'practitioner_id' => $consultation->practitioner_id,
                'prescribed_at' => $validated['prescribed_at'] ?? now(),
                'notes_encrypted' => $validated['notes'] ?? null,
            ]);

            /** @var array<int, array<string, mixed>> $items */
            $items = $validated['items'];

            foreach ($items as $item) {
                HealthPrescriptionItem::query()->create([
                    'company_id' => $actor->company_id,
                    'prescription_id' => (int) $prescription->getAttribute('id'),
                    'medication' => $item['medication'],
                    'dosage' => $item['dosage'] ?? null,
                    'frequency' => $item['frequency'] ?? null,
                    'duration' => $item['duration'] ?? null,
                    'instructions' => $item['instructions'] ?? null,
                ]);
            }

            return $prescription;
        });
    }
}
