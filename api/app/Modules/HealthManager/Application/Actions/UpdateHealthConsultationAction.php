<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Application\Actions;

use App\Modules\HealthManager\Domain\Models\HealthConsultation;

/**
 * Cas d'usage « mettre à jour une consultation » — HC-005 (#7789, BC-31).
 *
 * Extrait de `HealthConsultationController::update` (BOS-024b, #8213) :
 * même mapping clairs API → colonnes chiffrées qu'à la création (source
 * unique : `RecordHealthConsultationAction::medicalAttributes()`), plus
 * `consulted_at` / `reason` quand présents.
 */
final class UpdateHealthConsultationAction
{
    /**
     * @param  array<string, mixed>  $validated  Payload validé (UpdateHealthConsultationRequest).
     */
    public function execute(HealthConsultation $consultation, array $validated): HealthConsultation
    {
        $attributes = RecordHealthConsultationAction::medicalAttributes($validated);

        foreach (['consulted_at', 'reason'] as $field) {
            if (array_key_exists($field, $validated)) {
                $attributes[$field] = $validated[$field];
            }
        }

        $consultation->update($attributes);

        return $consultation->refresh();
    }
}
