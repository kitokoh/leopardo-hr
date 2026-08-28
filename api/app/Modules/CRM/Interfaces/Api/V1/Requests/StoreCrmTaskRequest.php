<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Requests;

use App\Modules\CRM\Domain\Enums\CrmRelatedType;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création d'une tâche CRM — Issue #5711 (CRM-V0-07).
 *
 * Tâches bornées : statuts/priorités allowlistés (alignés sur les CHECK en
 * base), assignee tenant-scopé, `related_type` enum. `completed_at` est
 * dérivé côté serveur (transition vers `done`). Champs inconnus refusés.
 */
class StoreCrmTaskRequest extends FormRequest
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
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'status' => ['nullable', 'in:todo,in_progress,done,cancelled'],
            'priority' => ['nullable', 'in:low,medium,high'],
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
