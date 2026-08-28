<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Requests;

use App\Modules\CRM\Domain\Models\CrmLead;
use Illuminate\Validation\Rule;

/**
 * Issue #5711 — Création d'un lead CRM client.
 *
 * Statuts/priorités allowlistés (constantes du domaine), `owner_id`
 * validé dans le tenant courant (employees), champs inconnus refusés.
 */
class StoreCrmLeadRequest extends BaseCrmRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'company_name' => ['nullable', 'string', 'max:150'],
            'source' => ['nullable', 'string', 'max:40'],
            'status' => ['sometimes', Rule::in(CrmLead::STATUSES)],
            'priority' => ['sometimes', Rule::in(CrmLead::PRIORITIES)],
            'owner_id' => ['nullable', 'integer', Rule::exists('employees', 'id')->where('company_id', currentCompany()->id)],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
