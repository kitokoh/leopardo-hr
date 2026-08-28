<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Requests;

use App\Modules\CRM\Domain\Support\SegmentDefinitionValidator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Mise à jour d'un segment CRM — Issue #5723.
 */
class UpdateSegmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'definition' => ['sometimes', 'array'],
            'definition.operator' => ['required_with:definition', 'string', 'in:and,or'],
            'definition.conditions' => ['required_with:definition', 'array', 'min:1', 'max:'.SegmentDefinitionValidator::MAX_CONDITIONS],
            'is_active' => ['sometimes', 'boolean'],
            'id' => ['prohibited'],
            'company_id' => ['prohibited'],
        ];
    }
}
