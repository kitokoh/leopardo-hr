<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Requests;

use App\Modules\CRM\Domain\Models\CrmTask;
use Illuminate\Validation\Rule;

/**
 * Issue #5711 — Création d'une tâche CRM client.
 */
class StoreCrmTaskRequest extends BaseCrmRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:10000'],
            'status' => ['sometimes', Rule::in(CrmTask::STATUSES)],
            'priority' => ['sometimes', Rule::in(CrmTask::PRIORITIES)],
            'due_at' => ['nullable', 'date'],
            'assigned_to' => ['nullable', 'integer', Rule::exists('employees', 'id')->where('company_id', currentCompany()->id)],
            'account_id' => ['nullable', 'integer'],
            'contact_id' => ['nullable', 'integer'],
            'lead_id' => ['nullable', 'integer'],
            'opportunity_id' => ['nullable', 'integer'],
        ];
    }
}
