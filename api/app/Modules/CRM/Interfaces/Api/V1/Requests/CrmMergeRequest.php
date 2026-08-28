<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Requests;

use App\Modules\CRM\Domain\Enums\CrmMergeEntityType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * #5718 — Validation stricte de la fusion supervisée (ADR-CRM-005).
 *
 * Entité whitelistée, ids numériques distincts (même id = 422), limites
 * bornées pour les suggestions.
 */
class CrmMergeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // la Policy (CrmMergePolicy) tranche l'autorisation
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'entity' => ['required', 'string', Rule::in(array_column(CrmMergeEntityType::cases(), 'value'))],
            'winner_id' => ['required', 'integer', 'different:loser_id'],
            'loser_id' => ['required', 'integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'entity.in' => 'Entité inconnue (accounts, contacts ou leads).',
            'winner_id.required' => 'Le compte gagnant est requis.',
            'loser_id.required' => 'Le compte perdant est requis.',
            'winner_id.different' => 'Impossible de fusionner une entité avec elle-même.',
        ];
    }
}
