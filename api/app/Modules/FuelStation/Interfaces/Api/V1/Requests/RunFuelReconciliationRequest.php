<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Déclenchement d'un rapprochement stock (FUEL-009, issue #5803).
 *
 * `run_date` optionnel (défaut : aujourd'hui, fuseau de la station côté
 * contrôleur). Le rapprochement est rejouable : un run existant pour la
 * même (station, date) est renvoyé sans recalcul.
 */
class RunFuelReconciliationRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'run_date' => ['nullable', 'date', 'before_or_equal:today'],
        ];
    }
}
