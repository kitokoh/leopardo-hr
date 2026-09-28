<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\HealthManager\Domain\Access\HealthAccess;
use App\Modules\HealthManager\Domain\Models\HealthAppointment;
use App\Modules\HealthManager\Domain\Models\HealthConsultation;
use App\Modules\HealthManager\Domain\Models\HealthPatient;
use App\Modules\HealthManager\Domain\Models\HealthPractitioner;
use Illuminate\Validation\ValidationException;

/**
 * Cas d'usage « enregistrer une consultation » — HC-005 (#7789, BC-31).
 *
 * Extrait de `HealthConsultationController::store` (BOS-024b, #8213) :
 * patient (et rendez-vous optionnel) du MÊME tenant (404 fail-closed) ;
 * praticien acteur → SA fiche (forcé, jamais celle d'un tiers), la
 * direction peut désigner un praticien du tenant ; contenu médical mappé
 * vers les colonnes chiffrées au repos.
 */
final class RecordHealthConsultationAction
{
    /**
     * Clairs API médicaux ↔ colonnes chiffrées du modèle. Source unique,
     * partagée avec `UpdateHealthConsultationAction`.
     *
     * @var array<string, string>
     */
    public const MEDICAL_INPUTS = [
        'clinical_exam' => 'clinical_exam_encrypted',
        'diagnosis' => 'diagnosis_encrypted',
        'vitals' => 'vitals_encrypted',
        'notes' => 'notes_encrypted',
    ];

    /**
     * @param  array<string, mixed>  $validated  Payload validé (StoreHealthConsultationRequest).
     */
    public function execute(Employee $actor, array $validated): HealthConsultation
    {
        // Patient du MÊME tenant (cross-tenant → 404 fail-closed).
        HealthPatient::query()
            ->where('company_id', $actor->company_id)
            ->whereKey((int) $validated['patient_id'])
            ->firstOrFail();

        // Rendez-vous optionnel, même tenant (404 fail-closed).
        if (isset($validated['appointment_id'])) {
            HealthAppointment::query()
                ->where('company_id', $actor->company_id)
                ->whereKey((int) $validated['appointment_id'])
                ->firstOrFail();
        }

        /** @var HealthConsultation $consultation */
        $consultation = HealthConsultation::query()->create(array_merge(
            self::medicalAttributes($validated),
            [
                'company_id' => $actor->company_id,
                'patient_id' => (int) $validated['patient_id'],
                'practitioner_id' => $this->resolvePractitionerId($actor, $validated),
                'appointment_id' => isset($validated['appointment_id']) ? (int) $validated['appointment_id'] : null,
                'consulted_at' => $validated['consulted_at'],
                'reason' => $validated['reason'] ?? null,
            ]
        ));

        return $consultation;
    }

    /**
     * Mappe l'API (clinical_exam, diagnosis, vitals, notes) vers les
     * colonnes chiffrées au repos (`*_encrypted`).
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public static function medicalAttributes(array $validated): array
    {
        $attributes = [];

        foreach (self::MEDICAL_INPUTS as $input => $column) {
            if (array_key_exists($input, $validated)) {
                $attributes[$column] = $validated[$input];
            }
        }

        return $attributes;
    }

    /**
     * Praticien acteur → SA fiche (forcé, jamais celle d'un tiers) ;
     * la direction peut désigner explicitement un praticien du tenant.
     *
     * @param  array<string, mixed>  $validated
     */
    private function resolvePractitionerId(Employee $actor, array $validated): int
    {
        if (HealthAccess::isAdmin($actor) && isset($validated['practitioner_id'])) {
            /** @var HealthPractitioner $practitioner */
            $practitioner = HealthPractitioner::query()
                ->where('company_id', $actor->company_id)
                ->whereKey((int) $validated['practitioner_id'])
                ->firstOrFail();

            return (int) $practitioner->getAttribute('id');
        }

        $ownId = HealthAccess::practitionerId($actor);

        if ($ownId === null) {
            // Message rendu à l'identique (clé JSON = texte source) —
            // déplacé tel quel depuis le controller (garde i18n #5432).
            throw ValidationException::withMessages([
                'practitioner_id' => [__('Le praticien de la consultation est requis.')],
            ]);
        }

        return $ownId;
    }
}
