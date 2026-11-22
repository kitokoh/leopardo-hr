<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Requests;

use App\Modules\CRM\Domain\Enums\CrmOpportunityStage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * #9790 — Déplacement d'une opportunité dans le pipeline.
 *
 * `stage` est validé contre la whitelist `CrmOpportunityStage` : la colonne
 * `crm_opportunities.stage` n'est qu'un `string(80)`, donc sans cette règle
 * n'importe quelle chaîne entrerait et le kanban afficherait une colonne
 * fantôme qu'aucun filtre ne retrouverait.
 *
 * `lost_reason` est **obligatoire** pour `lost` et **interdit** ailleurs :
 * perdre une affaire sans motif rend le pipeline inexploitable en revue
 * commerciale, et un motif sur une affaire gagnée est une donnée trompeuse.
 */
final class UpdateCrmOpportunityStageRequest extends FormRequest
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
        return [
            'stage' => ['required', 'string', Rule::enum(CrmOpportunityStage::class)],
            'lost_reason' => [
                Rule::requiredIf(fn (): bool => $this->input('stage') === CrmOpportunityStage::Lost->value),
                'nullable',
                'string',
                'max:500',
                Rule::prohibitedIf(fn (): bool => $this->input('stage') !== CrmOpportunityStage::Lost->value),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'stage.required' => 'L\'étape cible est obligatoire.',
            'stage.enum' => 'Cette étape n\'existe pas dans le pipeline.',
            'lost_reason.required' => 'Un motif est obligatoire pour marquer une affaire perdue.',
            'lost_reason.prohibited' => 'Un motif de perte n\'a de sens que pour une affaire perdue.',
        ];
    }
}
