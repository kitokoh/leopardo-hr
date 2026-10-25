<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Issue #5720 — Création de tâche CRM (champs bornés, enums fermés).
 */
class CrmStoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'due_at' => ['nullable', 'date'],
            // #5720 — `status` était absent de l'allowlist : un statut inconnu
            // (`weird`) passait la validation et créait la tâche (201) au lieu
            // d'être rejeté (422). Même enum fermé que CrmUpdateTaskRequest.
            'status' => ['sometimes', 'string', 'in:todo,in_progress,done,cancelled'],
            'priority' => ['sometimes', 'string', 'in:low,medium,high'],
            'assignee_id' => ['nullable', 'integer', 'min:1'],
            'account_id' => ['nullable', 'integer', 'min:1'],
            'contact_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
