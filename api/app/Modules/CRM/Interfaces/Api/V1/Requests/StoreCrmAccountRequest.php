<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création d'un compte CRM — Issue #5711 (CRM-V0-07).
 *
 * `status` / `source` allowlistés (alignés sur les CHECK en base), PII
 * validée (email, pays ISO-2), `owner_id` tenant-scopé. Champs inconnus
 * refusés.
 */
class StoreCrmAccountRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:191'],
            'legal_name' => ['nullable', 'string', 'max:191'],
            'industry' => ['nullable', 'string', 'max:100'],
            'website' => ['nullable', 'string', 'max:255', 'url'],
            'email' => ['nullable', 'string', 'max:191', 'email:rfc'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'size:2'],
            'tax_id' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'in:active,inactive,archived'],
            'source' => ['nullable', 'in:manual,import,web,referral,other'],
            'owner_id' => [
                'nullable',
                'integer',
                'min:1',
                Rule::exists('employees', 'id')->where('company_id', $this->user()?->company_id),
            ],
            'metadata' => ['nullable', 'array'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $this->rejectUnknownFields($validator);
    }
}
