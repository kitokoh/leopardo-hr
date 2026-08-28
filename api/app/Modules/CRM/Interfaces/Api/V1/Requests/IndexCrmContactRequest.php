<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Liste des contacts CRM — Issue #5711 (CRM-V0-07).
 *
 * Filtres allowlistés : compte tenant-scopé, statut borné, recherche sur
 * nom/email, pagination et tri bornés.
 */
class IndexCrmContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'account_id' => [
                'nullable',
                'integer',
                'min:1',
                Rule::exists('crm_accounts', 'id')->where('company_id', $this->user()?->company_id),
            ],
            'status' => ['nullable', 'in:active,inactive,archived'],
            'is_primary' => ['nullable', 'boolean'],
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'sort_by' => ['nullable', 'in:created_at,updated_at,last_name,email,status'],
            'sort_dir' => ['nullable', 'in:asc,desc'],
        ];
    }
}
