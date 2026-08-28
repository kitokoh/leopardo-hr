<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Requests;

use App\Modules\CRM\Domain\Enums\CrmRelatedType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Liste des tâches CRM — Issue #5711 (CRM-V0-07).
 *
 * Filtres allowlistés (status, priority, assignee tenant-scopé,
 * related_type enum, bornes de pagination et de tri).
 */
class IndexCrmTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['nullable', 'in:todo,in_progress,done,cancelled'],
            'priority' => ['nullable', 'in:low,medium,high'],
            'assignee_id' => [
                'nullable',
                'integer',
                'min:1',
                Rule::exists('employees', 'id')->where('company_id', $this->user()?->company_id),
            ],
            'related_type' => ['nullable', 'in:'.implode(',', CrmRelatedType::values())],
            'related_id' => ['nullable', 'integer', 'min:1', 'required_with:related_type'],
            'due_from' => ['nullable', 'date'],
            'due_to' => ['nullable', 'date', 'after_or_equal:due_from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'sort_by' => ['nullable', 'in:created_at,updated_at,due_at,status,priority'],
            'sort_dir' => ['nullable', 'in:asc,desc'],
        ];
    }
}
