<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Mise à jour d'une facture de soins (HC-007, #7791) — BROUILLON
 * UNIQUEMENT (une facture émise n'est plus modifiable, 422
 * HEALTH_INVOICE_NOT_EDITABLE côté contrôleur). Remplacer les lignes
 * re-fige les prix depuis le catalogue et recalcule le total serveur.
 */
class UpdateHealthInvoiceRequest extends FormRequest
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
        /** @var Employee|null $actor */
        $actor = $this->user();

        return [
            'discount' => ['sometimes', 'numeric', 'min:0', 'max:9999999999'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.care_act_id' => [
                'required_with:items',
                'integer',
                Rule::exists('health_care_acts', 'id')->where(
                    fn (Builder $query): Builder => $query
                        ->where('company_id', $actor?->company_id)
                        ->where('is_active', true)
                ),
            ],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1', 'max:1000'],
        ];
    }
}
