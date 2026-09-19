<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création d'une facture de soins BROUILLON — HC-007 (#7791).
 *
 * Lignes : soit un acte du catalogue (`care_act_id`, prix FIGÉ serveur —
 * tout prix client est ignoré), soit une ligne libre (`label` +
 * `unit_price`). Les totaux sont recalculés SERVEUR (spec §4) : aucun
 * champ subtotal/total accepté ici.
 */
class StoreHealthInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // RBAC porté par la policy (HealthInvoicePolicy@create).
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Employee|null $actor */
        $actor = $this->user();
        $companyId = $actor?->company_id;

        return [
            'patient_id' => [
                'required',
                'integer',
                Rule::exists('health_patients', 'id')->where(
                    fn (Builder $query): Builder => $query->where('company_id', $companyId)
                ),
            ],
            'currency' => ['nullable', 'string', 'size:3'],
            'discount' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.care_act_id' => [
                'nullable',
                'integer',
                Rule::exists('health_care_acts', 'id')->where(
                    fn (Builder $query): Builder => $query->where('company_id', $companyId)
                ),
            ],
            'items.*.label' => ['required_without:items.*.care_act_id', 'nullable', 'string', 'max:191'],
            'items.*.unit_price' => ['required_without:items.*.care_act_id', 'nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
        ];
    }
}
