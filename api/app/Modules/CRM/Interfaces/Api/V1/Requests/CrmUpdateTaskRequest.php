<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Issue #5720 — Mise à jour de tâche CRM (champs optionnels, enums fermés).
 */
class CrmUpdateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'due_at' => ['nullable', 'date'],
            'status' => ['sometimes', 'string', 'in:todo,in_progress,done,cancelled'],
            'priority' => ['sometimes', 'string', 'in:low,medium,high'],
            'assignee_id' => ['nullable', 'integer', 'min:1'],
            'account_id' => ['nullable', 'integer', 'min:1'],
            'contact_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
