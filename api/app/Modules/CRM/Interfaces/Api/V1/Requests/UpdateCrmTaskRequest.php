<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Requests;

use App\Modules\CRM\Domain\Enums\CrmRelatedType;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Mise à jour d'une tâche CRM — Issue #5711 (CRM-V0-07).
 *
 * `completed_at` est géré côté serveur : passer `status` à `done` horodate
 * la complétion (idempotent). Champs inconnus refusés.
 */
class UpdateCrmTaskRequest extends FormRequest
{
    use RejectsUnknownFields;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'subject' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'status' => ['sometimes', 'in:todo,in_progress,done,cancelled'],
            'priority' => ['sometimes', 'in:low,medium,high'],
            'due_at' => ['nullable', 'date'],
            'assignee_id' => [
                'nullable',
                'integer',
                'min:1',
                Rule::exists('employees', 'id')->where('company_id', $this->user()?->company_id),
            ],
            'related_type' => ['nullable', 'in:'.implode(',', CrmRelatedType::values())],
            'related_id' => ['nullable', 'integer', 'min:1', 'required_with:related_type'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $this->rejectUnknownFields($validator);
    }
}
