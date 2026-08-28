<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Requests;

use App\Modules\CRM\Domain\Enums\CrmOpportunityStage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * #5717 — Validation stricte de la conversion de lead (ADR-CRM-005).
 *
 * Tous les champs sont optionnels (la conversion fonctionne avec les seules
 * données du lead) ; les valeurs fournies sont whitelistées (étape) ou
 * bornées (montant, devise ISO 4217, date de clôture).
 */
class ConvertLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // la Policy (CrmLeadPolicy) tranche l'autorisation
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $stages = array_column(CrmOpportunityStage::cases(), 'value');

        return [
            'stage' => ['sometimes', 'string', Rule::in($stages)],
            'amount' => ['sometimes', 'numeric', 'min:0', 'max:999999999999.99'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'expected_close_date' => ['sometimes', 'date', 'after_or_equal:today'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'stage.in' => 'Étape inconnue (prospecting, qualification, proposal, negotiation, won, lost).',
            'amount.numeric' => 'Le montant doit être numérique.',
            'currency.size' => 'La devise doit être un code ISO 4217 à 3 lettres.',
            'expected_close_date.after_or_equal' => 'La date de clôture prévue ne peut pas être dans le passé.',
        ];
    }
}
