<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Requests;

use App\Modules\CRM\Domain\Models\CrmLead;
use Illuminate\Validation\Rule;

/**
 * Issue #5711 — Mise à jour d'un lead CRM client (tous champs optionnels).
 */
class UpdateCrmLeadRequest extends BaseCrmRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['sometimes', 'required', 'string', 'max:100'],
            'last_name' => ['sometimes', 'required', 'string', 'max:100'],
            'email' => ['sometimes', 'nullable', 'email', 'max:190'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:40'],
            'company_name' => ['sometimes', 'nullable', 'string', 'max:150'],
            'source' => ['sometimes', 'nullable', 'string', 'max:40'],
            'status' => ['sometimes', Rule::in(CrmLead::STATUSES)],
            'priority' => ['sometimes', Rule::in(CrmLead::PRIORITIES)],
            'owner_id' => ['sometimes', 'nullable', 'integer', Rule::exists('employees', 'id')->where('company_id', currentCompany()->id)],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }
}
