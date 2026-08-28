<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Liste des comptes CRM — Issue #5711 (CRM-V0-07).
 *
 * Filtres/tri/pagination allowlistés ; `owner_id` tenant-scopé.
 */
class IndexCrmAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['nullable', 'in:active,inactive,archived'],
            'source' => ['nullable', 'in:manual,import,web,referral,other'],
            'owner_id' => [
                'nullable',
                'integer',
                'min:1',
                Rule::exists('employees', 'id')->where('company_id', $this->user()?->company_id),
            ],
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'sort_by' => ['nullable', 'in:created_at,updated_at,name,status,industry'],
            'sort_dir' => ['nullable', 'in:asc,desc'],
        ];
    }
}
