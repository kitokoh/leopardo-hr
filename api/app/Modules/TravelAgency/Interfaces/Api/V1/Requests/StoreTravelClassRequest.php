<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * TRAVEL-305 (#6035) — Validation stricte de création d'une classe de
 * service (unicité `code` par tenant portée par la contrainte DB
 * `travel_classes_company_code_unique`).
 */
class StoreTravelClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // TravelClassPolicy::create() tranche l'autorisation
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:40'],
            'label' => ['required', 'string', 'max:120'],
            'color' => ['nullable', 'string', 'max:7'],
            'priority' => ['sometimes', 'integer', 'min:0', 'max:65535'],
            'status' => ['sometimes', 'string', Rule::in(['active', 'disabled'])],
        ];
    }
}
