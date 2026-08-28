<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Requests;

use App\Modules\CRM\Domain\Models\CrmTask;
use Illuminate\Validation\Rule;

/**
 * Issue #5711 — Mise à jour d'une tâche CRM client.
 *
 * Un assigné non-manager ne peut modifier que `status`/`priority` et la
 * complétion ; la Policy gère la portée, les règles restent communes.
 */
class UpdateCrmTaskRequest extends BaseCrmRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:200'],
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'status' => ['sometimes', Rule::in(CrmTask::STATUSES)],
            'priority' => ['sometimes', Rule::in(CrmTask::PRIORITIES)],
            'due_at' => ['sometimes', 'nullable', 'date'],
            'assigned_to' => ['sometimes', 'nullable', 'integer', Rule::exists('employees', 'id')->where('company_id', currentCompany()->id)],
            'account_id' => ['sometimes', 'nullable', 'integer'],
            'contact_id' => ['sometimes', 'nullable', 'integer'],
            'lead_id' => ['sometimes', 'nullable', 'integer'],
            'opportunity_id' => ['sometimes', 'nullable', 'integer'],
        ];
    }
}
