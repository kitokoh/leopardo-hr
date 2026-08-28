<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Requests;

use App\Modules\CRM\Domain\Models\CrmPipelineStage;
use Illuminate\Contracts\Validation\Validator;

/**
 * Issue #5711 — Mise à jour d'un stage de pipeline CRM client.
 */
class UpdateCrmPipelineStageRequest extends BaseCrmRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'position' => ['sometimes', 'required', 'integer', 'min:0'],
            'color' => ['sometimes', 'nullable', 'string', 'max:20'],
            'is_won' => ['sometimes', 'boolean'],
            'is_lost' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var CrmPipelineStage|null $stage */
            $stage = $this->route('crmPipelineStage');

            $isWon = (bool) $this->input('is_won', $stage instanceof CrmPipelineStage ? $stage->is_won : false);
            $isLost = (bool) $this->input('is_lost', $stage instanceof CrmPipelineStage ? $stage->is_lost : false);

            if ($isWon && $isLost) {
                $validator->errors()->add('is_lost', 'Un stage ne peut pas être à la fois gagnant et perdant.');
            }
        });
    }
}
