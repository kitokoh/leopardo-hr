<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Activation FuelStation — Issue #5795 (FUEL-001).
 *
 * Aucun champ attendu (activation idempotente du tenant courant) ; toute clé
 * inconnue est refusée (422).
 */
class ActivateFuelStationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * @param  \Illuminate\Validation\Validator  $validator
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $unknown = array_diff(array_keys($this->all()), []);
            if ($unknown !== []) {
                $validator->errors()->add('unknown_fields', 'Champs non autorisés : '.implode(', ', $unknown));
            }
        });
    }
}
