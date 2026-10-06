<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Requests;

use App\Modules\CRM\Domain\Enums\CrmLeadStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * #8195 / ADR-CRM-005 — Validation stricte de la création d'un prospect (lead).
 */
class StoreCrmLeadRequest extends FormRequest
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
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['nullable', 'string', 'max:120'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'source' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'string', Rule::in(array_map(fn (CrmLeadStatus $s): string => $s->value, CrmLeadStatus::cases()))],
            'notes' => ['nullable', 'string', 'max:5000'],
            'score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'tags' => ['nullable', 'array'],
        ];
    }
}
