<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Requests;

use Illuminate\Contracts\Validation\Validator;

/**
 * Issue #5711 — Création d'un stage de pipeline CRM client.
 *
 * `position` >= 0 (CHECK en base) et `is_won`/`is_lost` exclusifs (rejet
 * 422 ici plutôt que QueryException 500 en base).
 */
class StoreCrmPipelineStageRequest extends BaseCrmRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'position' => ['required', 'integer', 'min:0'],
            'color' => ['nullable', 'string', 'max:20'],
            'is_won' => ['sometimes', 'boolean'],
            'is_lost' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ((bool) $this->input('is_won') && (bool) $this->input('is_lost')) {
                $validator->errors()->add('is_lost', 'Un stage ne peut pas être à la fois gagnant et perdant.');
            }
        });
    }
}
