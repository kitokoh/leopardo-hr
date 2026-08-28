<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création d'un lead CRM — Issue #5711 (CRM-V0-07).
 *
 * Statuts/sources/priorités allowlistés (alignés sur les CHECK en base) ;
 * `owner_id` doit être un employé du MÊME tenant ; tout champ inconnu est
 * refusé (422, trait RejectsUnknownFields).
 */
class StoreCrmLeadRequest extends FormRequest
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
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'string', 'max:190', 'email:rfc'],
            'phone' => ['nullable', 'string', 'max:40'],
            'company_name' => ['nullable', 'string', 'max:150'],
            'source' => ['nullable', 'in:manual,import,web,referral,other'],
            'status' => ['nullable', 'in:new,contacted,qualified,converted,rejected'],
            'priority' => ['nullable', 'in:low,medium,high'],
            'owner_id' => [
                'nullable',
                'integer',
                'min:1',
                Rule::exists('employees', 'id')->where('company_id', $this->user()?->company_id),
            ],
            'notes' => ['nullable', 'string', 'max:10000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $this->rejectUnknownFields($validator);
    }
}
