<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * #8195 / ADR-CRM-005 — Validation stricte de la création d'un compte CRM.
 */
class StoreCrmAccountRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:60'],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive', 'archived'])],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
