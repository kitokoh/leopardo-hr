<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Requests;

use App\Modules\CRM\Domain\Enums\CrmRelatedType;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Ajout d'une activité à la timeline CRM — Issue #5711 (CRM-V0-07).
 *
 * Append-only : pas de route de mise à jour (la timeline est immuable).
 * `related_type` borné à l'enum ; `related_id` obligatoire quand
 * `related_type` est fourni. Champs inconnus refusés.
 */
class StoreCrmActivityRequest extends FormRequest
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
            'activity_type' => ['required', 'in:note,call,email,meeting,other'],
            'description' => ['nullable', 'string', 'max:10000'],
            'related_type' => ['nullable', 'in:'.implode(',', CrmRelatedType::values())],
            'related_id' => ['nullable', 'integer', 'min:1', 'required_with:related_type'],
            'owner_id' => [
                'nullable',
                'integer',
                'min:1',
                Rule::exists('employees', 'id')->where('company_id', $this->user()?->company_id),
            ],
            'happened_at' => ['nullable', 'date'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $this->rejectUnknownFields($validator);
    }
}
