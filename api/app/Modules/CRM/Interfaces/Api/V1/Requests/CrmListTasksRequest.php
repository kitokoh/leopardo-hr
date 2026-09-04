<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Issue #5720 — Filtres de liste des tâches CRM (strictement allowlistés).
 */
class CrmListTasksRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'string', 'in:todo,in_progress,done,cancelled'],
            'overdue' => ['sometimes', 'boolean'],
            'priority' => ['sometimes', 'string', 'in:low,medium,high'],
            'owner_id' => ['sometimes', 'integer', 'min:1'],
            'account_id' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
