<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Requests;

use App\Modules\CRM\Domain\Enums\CrmRelatedType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Liste de la timeline CRM (activities) — Issue #5711 (CRM-V0-07).
 *
 * Filtres allowlistés : `related_type` borné à l'enum (lead, opportunity,
 * contact, account), tri temporel borné, pagination bornée.
 */
class IndexCrmActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'activity_type' => ['nullable', 'in:note,call,email,meeting,other'],
            'related_type' => ['nullable', 'in:'.implode(',', CrmRelatedType::values())],
            'related_id' => ['nullable', 'integer', 'min:1', 'required_with:related_type'],
            'owner_id' => [
                'nullable',
                'integer',
                'min:1',
                Rule::exists('employees', 'id')->where('company_id', $this->user()?->company_id),
            ],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'sort_by' => ['nullable', 'in:created_at,happened_at,activity_type'],
            'sort_dir' => ['nullable', 'in:asc,desc'],
        ];
    }
}
